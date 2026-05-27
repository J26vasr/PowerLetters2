"""Hermes orchestrator: tool-calling loop con DeepSeek y emision de artifacts."""
import json
import os
from typing import Any
import httpx
from tools import schemas_for_role, execute_tool


DEEPSEEK_BASE_URL = os.environ.get("DEEPSEEK_BASE_URL", "https://api.deepseek.com/v1")
DEEPSEEK_API_KEY = os.environ["DEEPSEEK_API_KEY"]
DEEPSEEK_MODEL = os.environ.get("DEEPSEEK_MODEL", "deepseek-chat")

MAX_TOOL_ITERATIONS = 8
HTTP_TIMEOUT = 60.0


SYSTEM_PROMPT_CLIENT = """Sos Hermes, agente de IA de la libreria PowerLetters. Operas sobre la base de datos real del sistema y podes ejecutar acciones.

CAPACIDADES (tools):
- Catalogo: search_books, book_details, top_books
- Personalizado: recommend_for_user, my_orders
- Carrito: add_to_cart, view_cart, remove_from_cart, checkout

REGLAS CRITICAS — INTEGRIDAD DE DATOS:
1. **NUNCA inventes un id_libro**. Si vas a llamar add_to_cart, remove_from_cart o book_details, el id_libro DEBE haber salido literalmente de un resultado previo de search_books o top_books o recommend_for_user en ESTA conversacion. Si no estas 100% seguro de que el ID existe, llama search_books primero.
2. Si una tool retorna `{"error": "..."}` o `{"ok": false}`, la accion FALLO. NO digas "listo", "perfecto" ni "compra realizada". Decile al usuario que fallo y por que.
3. Si llamaste view_cart y el carrito tiene libros, NO digas que el pedido se finalizo a menos que TAMBIEN hayas llamado checkout en ESTA respuesta y la respuesta haya sido `{"ok": true, "pedido_id": N}`.
4. Si el usuario pide algo ambiguo (ej. "Poemas de amor" y hay 2 libros con "Poemas" en el titulo), MOSTRA las opciones (search_books) y pide que el usuario elija UNA. Nunca asumas cual.

REGLAS de respuesta:
1. NO uses emojis nunca.
2. Respuestas BREVES (1-3 oraciones). El sistema renderiza listas de libros y carrito como UI visual; no repitas los datos en texto. Cuando una tool ya devolvio datos, decis 1 frase introductoria y nada mas. NO armes tablas markdown.
3. Tono profesional directo.

REGLAS de compra (orden ESTRICTO):
1. Para agregar al carrito: primero search_books (para confirmar el ID real), despues add_to_cart con ese ID exacto.
2. Si el usuario no especifica cantidad: asumi 1 y mencionalo.
3. ANTES de checkout (turno A): llamas view_cart, presentas el resumen, preguntas "¿Confirmas finalizar el pedido?". Aca te detenes y esperas la respuesta del usuario.
4. Cuando el usuario respondio "si"/"confirmo" (turno B): recien ahora llamas checkout. Solo si retorna `{"ok": true, "pedido_id": N}` decis "Pedido #N finalizado". Si retorna error, decis el error literal.
5. PROHIBIDO afirmar "compra realizada" sin haber recibido `ok: true` de la tool checkout en el mismo turno.

REGLAS de UI:
- search_books / top_books / recommend_for_user / book_details: el sistema dibuja cards automaticamente. Tu respuesta es 1 oracion introductoria.
- view_cart: el sistema dibuja el carrito. Tu respuesta es la pregunta de confirmacion.
- checkout exitoso: el sistema dibuja el banner de confirmacion. Tu respuesta es 1 oracion de cierre.
"""

SYSTEM_PROMPT_ADMIN = """Sos Hermes, agente administrativo de PowerLetters. Operas sobre la base de datos real.

CAPACIDADES:
- Catalogo: search_books, book_details, top_books
- Gestion: create_book, delete_books_no_stock
- Reportes: top_sold_month, low_stock_alert

REGLAS:
1. NO uses emojis. Tono profesional, directo.
2. Respuestas breves. El sistema renderiza listas y reportes visualmente.
3. ANTES de ejecutar acciones destructivas (delete_books_no_stock, etc.) CONFIRMA con el usuario.
4. Para create_book, requieres todos los IDs (autor, genero, editorial, clasificacion). Si el usuario no los tiene, ayudalo a buscarlos primero con search_books o pidiendolos.
5. NO inventes datos. Si una tool falla, dilo claramente.
"""


def _system_prompt(role: str) -> str:
    return SYSTEM_PROMPT_ADMIN if role == "admin" else SYSTEM_PROMPT_CLIENT


def _make_artifacts(tool_log: list[dict]) -> list[dict]:
    """Toma el ultimo resultado de cada tool relevante y lo mapea a UI blocks."""
    artifacts: list[dict] = []
    last_by_name: dict[str, dict] = {}
    for tc in tool_log:
        last_by_name[tc["name"]] = tc

    def _result_dict(name):
        d = last_by_name.get(name)
        if not d:
            return None
        r = d.get("result")
        return r if isinstance(r, dict) else None

    for tool_name in ("search_books", "top_books", "recommend_for_user"):
        r = _result_dict(tool_name)
        if r and isinstance(r.get("books"), list) and r["books"]:
            artifacts.append({
                "type": "book_grid",
                "title": _grid_title(tool_name),
                "books": r["books"][:10],
            })
            break

    r = _result_dict("book_details")
    if r and isinstance(r.get("book"), dict):
        artifacts.append({"type": "book_detail", "book": r["book"]})

    r = _result_dict("view_cart")
    if r and isinstance(r.get("items"), list):
        artifacts.append({
            "type": "cart_panel",
            "items": r["items"],
            "total": r.get("total", 0.0),
            "item_count": r.get("item_count", 0),
        })

    r = _result_dict("checkout")
    if r and r.get("ok"):
        artifacts.append({
            "type": "order_confirmed",
            "order_id": r.get("pedido_id"),
            "total": r.get("total"),
            "items": r.get("items"),
        })

    r = _result_dict("my_orders")
    if r and isinstance(r.get("orders"), list) and r["orders"]:
        artifacts.append({"type": "orders_list", "orders": r["orders"][:10]})

    r = _result_dict("low_stock_alert")
    if r and isinstance(r.get("low_stock"), list):
        artifacts.append({
            "type": "low_stock",
            "books": r["low_stock"],
            "threshold": r.get("threshold"),
        })

    return artifacts


def _grid_title(tool_name: str) -> str:
    return {
        "search_books":         "Resultados de busqueda",
        "top_books":            "Mas vendidos",
        "recommend_for_user":   "Recomendados para vos",
    }.get(tool_name, "Libros")


async def chat(messages: list[dict], role: str, user_id: int | None) -> dict:
    if not messages or messages[0].get("role") != "system":
        messages = [{"role": "system", "content": _system_prompt(role)}] + messages

    tools = schemas_for_role(role)
    tool_log: list[dict] = []

    async with httpx.AsyncClient(timeout=HTTP_TIMEOUT) as client:
        for iteration in range(MAX_TOOL_ITERATIONS):
            resp = await client.post(
                f"{DEEPSEEK_BASE_URL}/chat/completions",
                headers={"Authorization": f"Bearer {DEEPSEEK_API_KEY}"},
                json={
                    "model": DEEPSEEK_MODEL,
                    "messages": messages,
                    "tools": tools,
                    "tool_choice": "auto",
                    "temperature": 0.3,
                },
            )
            resp.raise_for_status()
            data = resp.json()
            msg = data["choices"][0]["message"]

            if not msg.get("tool_calls"):
                return {
                    "reply": msg.get("content") or "",
                    "artifacts": _make_artifacts(tool_log),
                    "tool_log": [
                        {"name": t["name"], "args": t["args"]}
                        for t in tool_log
                    ],
                }

            messages.append({
                "role": "assistant",
                "content": msg.get("content") or "",
                "tool_calls": msg["tool_calls"],
            })

            for tc in msg["tool_calls"]:
                fn_name = tc["function"]["name"]
                try:
                    fn_args = json.loads(tc["function"]["arguments"] or "{}")
                except json.JSONDecodeError:
                    fn_args = {}
                result = execute_tool(fn_name, fn_args, role, user_id)
                tool_log.append({"name": fn_name, "args": fn_args, "result": result})
                messages.append({
                    "role": "tool",
                    "tool_call_id": tc["id"],
                    "content": json.dumps(result, default=str, ensure_ascii=False),
                })

        return {
            "reply": "La consulta se hizo demasiado larga. Probemos algo mas concreto.",
            "artifacts": _make_artifacts(tool_log),
            "tool_log": [{"name": t["name"], "args": t["args"]} for t in tool_log],
        }
