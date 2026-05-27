"""Tools que el agente puede invocar. Cada una retorna un dict serializable."""
import json
from typing import Any
from db import query, query_one, execute, transaction


def search_books(query_text: str, limit: int = 5) -> dict:
    """Busqueda full-text sobre titulo, descripcion, autor, genero y clasificacion."""
    limit = min(max(limit, 1), 20)
    like = f"%{query_text}%"
    rows = query(
        """
        SELECT  l.id_libro, l.titulo, l.precio, l.existencias,
                a.nombre  AS nombre_autor,
                c.nombre  AS clasificacion,
                e.nombre  AS editorial,
                g.nombre  AS genero
          FROM tb_libros l
          LEFT JOIN tb_autores a         ON l.id_autor = a.id_autor
          LEFT JOIN tb_clasificaciones c ON l.id_clasificacion = c.id_clasificacion
          LEFT JOIN tb_editoriales e     ON l.id_editorial = e.id_editorial
          LEFT JOIN tb_generos g         ON l.id_genero = g.id_genero
         WHERE  l.titulo      LIKE %s
            OR  l.descripcion LIKE %s
            OR  a.nombre      LIKE %s
            OR  g.nombre      LIKE %s
            OR  c.nombre      LIKE %s
         LIMIT %s
        """,
        (like, like, like, like, like, limit),
    )
    return {"found": len(rows), "books": rows}


def book_details(book_id: int) -> dict:
    rows = query(
        """
        SELECT  l.*,
                a.nombre AS nombre_autor,
                c.nombre AS nombre_clasificacion,
                e.nombre AS nombre_editorial,
                g.nombre AS nombre_genero
          FROM tb_libros l
          LEFT JOIN tb_autores a         ON l.id_autor = a.id_autor
          LEFT JOIN tb_clasificaciones c ON l.id_clasificacion = c.id_clasificacion
          LEFT JOIN tb_editoriales e     ON l.id_editorial = e.id_editorial
          LEFT JOIN tb_generos g         ON l.id_genero = g.id_genero
         WHERE l.id_libro = %s
        """,
        (book_id,),
    )
    if not rows:
        return {"error": "Libro no encontrado"}
    return {"book": rows[0]}


def top_books(limit: int = 5) -> dict:
    """Libros mas vendidos historicamente (por suma de cantidad en detalles de pedidos entregados/finalizados)."""
    limit = min(max(limit, 1), 20)
    rows = query(
        """
        SELECT  l.id_libro, l.titulo, l.precio, l.existencias, l.imagen,
                a.nombre AS nombre_autor,
                COALESCE(SUM(dp.cantidad), 0) AS total_vendido
          FROM tb_libros l
          LEFT JOIN tb_autores a ON l.id_autor = a.id_autor
          LEFT JOIN tb_detalle_pedidos dp ON l.id_libro = dp.id_libro
          LEFT JOIN tb_pedidos p ON dp.id_pedido = p.id_pedido
                                  AND p.estado IN ('FINALIZADO', 'ENTREGADO')
         GROUP BY l.id_libro, l.titulo, l.precio, l.existencias, l.imagen, a.nombre
         ORDER BY total_vendido DESC
         LIMIT %s
        """,
        (limit,),
    )
    return {"books": rows}


def recommend_for_user(user_id: int, limit: int = 5) -> dict:
    """
    Recomienda libros segun el historial del cliente:
    busca libros del mismo genero y clasificacion que ya compro,
    excluyendo los que ya tiene.
    """
    limit = min(max(limit, 1), 10)
    rows = query(
        """
        SELECT  l.id_libro, l.titulo, l.precio, l.existencias, l.imagen,
                a.nombre AS nombre_autor,
                g.nombre AS nombre_genero,
                c.nombre AS nombre_clasificacion,
                COUNT(*) AS afinidad
          FROM tb_libros l
          LEFT JOIN tb_autores a         ON l.id_autor = a.id_autor
          LEFT JOIN tb_generos g         ON l.id_genero = g.id_genero
          LEFT JOIN tb_clasificaciones c ON l.id_clasificacion = c.id_clasificacion
         WHERE l.id_libro NOT IN (
                SELECT dp.id_libro
                  FROM tb_detalle_pedidos dp
                  JOIN tb_pedidos p ON dp.id_pedido = p.id_pedido
                 WHERE p.id_usuario = %s
           )
           AND (
                l.id_genero IN (
                    SELECT DISTINCT l2.id_genero
                      FROM tb_libros l2
                      JOIN tb_detalle_pedidos dp ON l2.id_libro = dp.id_libro
                      JOIN tb_pedidos p ON dp.id_pedido = p.id_pedido
                     WHERE p.id_usuario = %s
                )
             OR l.id_clasificacion IN (
                    SELECT DISTINCT l2.id_clasificacion
                      FROM tb_libros l2
                      JOIN tb_detalle_pedidos dp ON l2.id_libro = dp.id_libro
                      JOIN tb_pedidos p ON dp.id_pedido = p.id_pedido
                     WHERE p.id_usuario = %s
                )
           )
           AND l.existencias > 0
         GROUP BY l.id_libro, l.titulo, l.precio, l.existencias, l.imagen,
                  a.nombre, g.nombre, c.nombre
         ORDER BY afinidad DESC, l.id_libro DESC
         LIMIT %s
        """,
        (user_id, user_id, user_id, limit),
    )
    if not rows:
        # Fallback: si no tiene historial, recomienda los top books
        return top_books(limit)
    return {"books": rows, "based_on": "historial de compra"}


def my_orders(user_id: int) -> dict:
    """Historial de pedidos del usuario actual."""
    rows = query(
        """
        SELECT  p.id_pedido, p.estado, p.fecha_pedido,
                COUNT(dp.id_detalle) AS items,
                SUM(dp.cantidad * dp.precio) AS total
          FROM tb_pedidos p
          LEFT JOIN tb_detalle_pedidos dp ON p.id_pedido = dp.id_pedido
         WHERE p.id_usuario = %s
         GROUP BY p.id_pedido, p.estado, p.fecha_pedido
         ORDER BY p.fecha_pedido DESC
         LIMIT 20
        """,
        (user_id,),
    )
    return {"orders": rows}


def _get_or_create_cart(tx, user_id: int) -> int:
    """Devuelve el id_pedido PENDIENTE del usuario o crea uno nuevo."""
    pending = tx.query_one(
        """SELECT id_pedido FROM tb_pedidos
            WHERE id_usuario = %s AND estado = 'PENDIENTE'
            LIMIT 1""",
        (user_id,),
    )
    if pending:
        return pending["id_pedido"]
    # Crear nuevo pedido pendiente usando la direccion del usuario
    tx.execute(
        """INSERT INTO tb_pedidos (direccion_pedido, id_usuario)
            VALUES (
              (SELECT direccion_usuario FROM tb_usuarios WHERE id_usuario = %s),
              %s
            )""",
        (user_id, user_id),
    )
    return tx.lastrowid


def add_to_cart(user_id: int, book_id: int, quantity: int = 1) -> dict:
    """Agrega un libro al carrito (pedido PENDIENTE). Decrementa el stock."""
    if quantity < 1:
        return {"error": "La cantidad debe ser al menos 1."}
    if quantity > 50:
        return {"error": "Cantidad demasiado alta (max 50 por item)."}

    try:
        with transaction() as tx:
            book = tx.query_one(
                """SELECT id_libro, titulo, precio, existencias
                     FROM tb_libros WHERE id_libro = %s""",
                (book_id,),
            )
            if not book:
                return {"error": f"No existe el libro con id {book_id}."}
            if book["existencias"] < quantity:
                return {"error": f"Stock insuficiente. Solo hay {book['existencias']} unidades de '{book['titulo']}'."}

            cart_id = _get_or_create_cart(tx, user_id)

            # Si ya existe ese libro en el carrito, sumamos cantidades
            existing = tx.query_one(
                """SELECT id_detalle, cantidad FROM tb_detalle_pedidos
                    WHERE id_pedido = %s AND id_libro = %s""",
                (cart_id, book_id),
            )
            if existing:
                tx.execute(
                    """UPDATE tb_detalle_pedidos SET cantidad = cantidad + %s
                        WHERE id_detalle = %s""",
                    (quantity, existing["id_detalle"]),
                )
            else:
                tx.execute(
                    """INSERT INTO tb_detalle_pedidos
                            (id_libro, cantidad, precio, id_pedido)
                       VALUES (%s, %s, %s, %s)""",
                    (book_id, quantity, book["precio"], cart_id),
                )

            # Decrementar stock
            tx.execute(
                """UPDATE tb_libros SET existencias = existencias - %s
                    WHERE id_libro = %s""",
                (quantity, book_id),
            )

            return {
                "ok": True,
                "added": {
                    "id_libro": book_id,
                    "titulo": book["titulo"],
                    "cantidad": quantity,
                    "precio_unitario": float(book["precio"]),
                    "subtotal": float(book["precio"]) * quantity,
                },
                "cart_id": cart_id,
            }
    except Exception as e:
        return {"error": f"Fallo agregar al carrito: {type(e).__name__}: {e}"}


def view_cart(user_id: int) -> dict:
    """Lista los items del carrito actual del usuario y el total."""
    pending = query_one(
        """SELECT id_pedido FROM tb_pedidos
            WHERE id_usuario = %s AND estado = 'PENDIENTE'
            LIMIT 1""",
        (user_id,),
    )
    if not pending:
        return {"items": [], "total": 0.0, "message": "Tu carrito esta vacio."}

    items = query(
        """SELECT dp.id_detalle, dp.id_libro, l.titulo,
                  dp.cantidad, dp.precio,
                  (dp.cantidad * dp.precio) AS subtotal
             FROM tb_detalle_pedidos dp
             JOIN tb_libros l ON dp.id_libro = l.id_libro
            WHERE dp.id_pedido = %s
            ORDER BY dp.id_detalle""",
        (pending["id_pedido"],),
    )
    total = sum(float(i["subtotal"]) for i in items)
    return {
        "cart_id": pending["id_pedido"],
        "items": items,
        "total": round(total, 2),
        "item_count": len(items),
    }


def remove_from_cart(user_id: int, book_id: int) -> dict:
    """Quita un libro del carrito. Devuelve el stock al inventario."""
    try:
        with transaction() as tx:
            pending = tx.query_one(
                """SELECT id_pedido FROM tb_pedidos
                    WHERE id_usuario = %s AND estado = 'PENDIENTE'""",
                (user_id,),
            )
            if not pending:
                return {"error": "No tienes un carrito activo."}

            detail = tx.query_one(
                """SELECT id_detalle, cantidad FROM tb_detalle_pedidos
                    WHERE id_pedido = %s AND id_libro = %s""",
                (pending["id_pedido"], book_id),
            )
            if not detail:
                return {"error": f"El libro id {book_id} no esta en tu carrito."}

            tx.execute(
                "DELETE FROM tb_detalle_pedidos WHERE id_detalle = %s",
                (detail["id_detalle"],),
            )
            tx.execute(
                """UPDATE tb_libros SET existencias = existencias + %s
                    WHERE id_libro = %s""",
                (detail["cantidad"], book_id),
            )
            return {"ok": True, "removed_book_id": book_id, "restored_stock": detail["cantidad"]}
    except Exception as e:
        return {"error": f"Fallo eliminar del carrito: {type(e).__name__}: {e}"}


def checkout(user_id: int) -> dict:
    """
    Finaliza el carrito actual (estado PENDIENTE -> FINALIZADO).
    IMPORTANTE: el agente debe haber confirmado con el usuario antes de llamar esto.
    """
    try:
        with transaction() as tx:
            pending = tx.query_one(
                """SELECT id_pedido FROM tb_pedidos
                    WHERE id_usuario = %s AND estado = 'PENDIENTE'""",
                (user_id,),
            )
            if not pending:
                return {"error": "No tienes un carrito activo para finalizar."}

            # Verificar que el carrito no este vacio
            items = tx.query_one(
                "SELECT COUNT(*) AS c FROM tb_detalle_pedidos WHERE id_pedido = %s",
                (pending["id_pedido"],),
            )
            if not items or items["c"] == 0:
                return {"error": "Tu carrito esta vacio. Agrega libros antes de finalizar."}

            # Calcular total
            tot = tx.query_one(
                """SELECT SUM(cantidad * precio) AS total, COUNT(*) AS items
                     FROM tb_detalle_pedidos WHERE id_pedido = %s""",
                (pending["id_pedido"],),
            )

            tx.execute(
                """UPDATE tb_pedidos
                      SET estado = 'FINALIZADO', fecha_pedido = NOW()
                    WHERE id_pedido = %s""",
                (pending["id_pedido"],),
            )
            return {
                "ok": True,
                "pedido_id": pending["id_pedido"],
                "items": tot["items"],
                "total": float(tot["total"]),
                "message": f"Pedido #{pending['id_pedido']} finalizado correctamente.",
            }
    except Exception as e:
        return {"error": f"Fallo al finalizar pedido: {type(e).__name__}: {e}"}


def create_book(titulo: str, id_autor: int, id_genero: int,
                id_editorial: int, id_clasificacion: int,
                precio: float, existencias: int = 0,
                descripcion: str = "") -> dict:
    """Inserta un libro nuevo en el catalogo."""
    rows = execute(
        """
        INSERT INTO tb_libros
            (titulo, id_autor, id_genero, id_editorial, id_clasificacion,
             precio, existencias, descripcion, imagen)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 'default.jpg')
        """,
        (titulo, id_autor, id_genero, id_editorial, id_clasificacion,
         precio, existencias, descripcion),
    )
    return {"created": rows, "titulo": titulo}


def delete_books_no_stock() -> dict:
    """Elimina todos los libros con existencias = 0 (no referenciados en pedidos)."""
    rows = execute(
        """
        DELETE FROM tb_libros
         WHERE existencias = 0
           AND id_libro NOT IN (SELECT DISTINCT id_libro FROM tb_detalle_pedidos)
        """
    )
    return {"deleted": rows}


def top_sold_month(month: int | None = None, year: int | None = None,
                   limit: int = 10) -> dict:
    """Libros mas vendidos en un mes especifico. Si no se pasa, usa el mes actual."""
    from datetime import datetime
    now = datetime.now()
    month = month or now.month
    year = year or now.year
    limit = min(max(limit, 1), 20)
    rows = query(
        """
        SELECT  l.id_libro, l.titulo,
                a.nombre AS nombre_autor,
                SUM(dp.cantidad) AS unidades_vendidas,
                SUM(dp.cantidad * dp.precio) AS ingresos
          FROM tb_libros l
          JOIN tb_autores a ON l.id_autor = a.id_autor
          JOIN tb_detalle_pedidos dp ON l.id_libro = dp.id_libro
          JOIN tb_pedidos p ON dp.id_pedido = p.id_pedido
         WHERE MONTH(p.fecha_pedido) = %s
           AND YEAR(p.fecha_pedido)  = %s
           AND p.estado IN ('FINALIZADO', 'ENTREGADO')
         GROUP BY l.id_libro, l.titulo, a.nombre
         ORDER BY unidades_vendidas DESC
         LIMIT %s
        """,
        (month, year, limit),
    )
    return {"month": month, "year": year, "books": rows}


def low_stock_alert(threshold: int = 5) -> dict:
    """Alerta de libros con stock bajo (debajo del threshold)."""
    threshold = max(threshold, 0)
    rows = query(
        """
        SELECT  l.id_libro, l.titulo, l.existencias,
                a.nombre AS nombre_autor
          FROM tb_libros l
          LEFT JOIN tb_autores a ON l.id_autor = a.id_autor
         WHERE l.existencias <= %s
         ORDER BY l.existencias ASC
         LIMIT 30
        """,
        (threshold,),
    )
    return {"threshold": threshold, "low_stock": rows, "count": len(rows)}


TOOL_SCHEMAS = [
    {
        "type": "function",
        "function": {
            "name": "search_books",
            "description": "Busca libros en el catalogo por titulo, autor, genero, clasificacion o palabra clave.",
            "parameters": {
                "type": "object",
                "properties": {
                    "query_text": {"type": "string", "description": "Texto a buscar"},
                    "limit": {"type": "integer", "default": 5},
                },
                "required": ["query_text"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "book_details",
            "description": "Devuelve la ficha completa de un libro por su id.",
            "parameters": {
                "type": "object",
                "properties": {"book_id": {"type": "integer"}},
                "required": ["book_id"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "top_books",
            "description": "Top de libros mas vendidos historicamente.",
            "parameters": {
                "type": "object",
                "properties": {"limit": {"type": "integer", "default": 5}},
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "recommend_for_user",
            "description": "Recomienda libros al usuario actual segun su historial de compras. Solo usar si hay un cliente logueado.",
            "parameters": {
                "type": "object",
                "properties": {"limit": {"type": "integer", "default": 5}},
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "my_orders",
            "description": "Devuelve el historial de pedidos del cliente logueado.",
            "parameters": {"type": "object", "properties": {}},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "add_to_cart",
            "description": (
                "Agrega un libro al carrito del cliente. Decrementa el stock disponible. "
                "Si el libro ya esta en el carrito, suma la cantidad. "
                "ANTES de llamar a esta tool, asegurate que el usuario te haya confirmado "
                "el libro especifico (id_libro y cantidad)."
            ),
            "parameters": {
                "type": "object",
                "properties": {
                    "book_id": {"type": "integer", "description": "ID del libro a agregar"},
                    "quantity": {"type": "integer", "default": 1, "description": "Cantidad (1-50)"},
                },
                "required": ["book_id"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "view_cart",
            "description": "Lista los items que el cliente tiene en su carrito y el total a pagar.",
            "parameters": {"type": "object", "properties": {}},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "remove_from_cart",
            "description": "Elimina un libro del carrito del cliente. Devuelve las unidades al stock.",
            "parameters": {
                "type": "object",
                "properties": {
                    "book_id": {"type": "integer", "description": "ID del libro a quitar"},
                },
                "required": ["book_id"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "checkout",
            "description": (
                "Finaliza el carrito actual del cliente y crea el pedido definitivo. "
                "CRITICO: NUNCA llames esta tool sin antes haber mostrado al usuario "
                "los items del carrito y el total, y haber recibido confirmacion explicita "
                "para finalizar."
            ),
            "parameters": {"type": "object", "properties": {}},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "create_book",
            "description": "ADMIN. Crea un libro nuevo en el catalogo. Requiere todos los IDs ya existentes en sus tablas.",
            "parameters": {
                "type": "object",
                "properties": {
                    "titulo": {"type": "string"},
                    "id_autor": {"type": "integer"},
                    "id_genero": {"type": "integer"},
                    "id_editorial": {"type": "integer"},
                    "id_clasificacion": {"type": "integer"},
                    "precio": {"type": "number"},
                    "existencias": {"type": "integer", "default": 0},
                    "descripcion": {"type": "string", "default": ""},
                },
                "required": ["titulo", "id_autor", "id_genero",
                             "id_editorial", "id_clasificacion", "precio"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "delete_books_no_stock",
            "description": "ADMIN. Elimina todos los libros con existencias=0 que no esten referenciados en pedidos.",
            "parameters": {"type": "object", "properties": {}},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "top_sold_month",
            "description": "ADMIN. Top de libros mas vendidos en un mes. Si no se pasa mes/anio usa el actual.",
            "parameters": {
                "type": "object",
                "properties": {
                    "month": {"type": "integer"},
                    "year": {"type": "integer"},
                    "limit": {"type": "integer", "default": 10},
                },
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "low_stock_alert",
            "description": "ADMIN. Lista libros con stock bajo (debajo del threshold).",
            "parameters": {
                "type": "object",
                "properties": {"threshold": {"type": "integer", "default": 5}},
            },
        },
    },
]


CLIENT_TOOLS = {"search_books", "book_details", "top_books",
                "recommend_for_user", "my_orders",
                "add_to_cart", "view_cart", "remove_from_cart", "checkout"}
ADMIN_TOOLS = {"search_books", "book_details", "top_books",
               "create_book", "delete_books_no_stock",
               "top_sold_month", "low_stock_alert"}


def schemas_for_role(role: str) -> list[dict]:
    allowed = CLIENT_TOOLS if role == "client" else ADMIN_TOOLS
    return [t for t in TOOL_SCHEMAS if t["function"]["name"] in allowed]


def execute_tool(name: str, args: dict, role: str, user_id: int | None) -> Any:
    """Ejecuta una tool con sus argumentos y devuelve el resultado serializable."""
    allowed = CLIENT_TOOLS if role == "client" else ADMIN_TOOLS
    if name not in allowed:
        return {"error": f"La tool '{name}' no esta disponible para tu rol ({role})."}

    # Tools que requieren user_id (cliente logueado)
    user_id_tools = {
        "recommend_for_user": lambda a: recommend_for_user(user_id=user_id, limit=a.get("limit", 5)),
        "my_orders":          lambda a: my_orders(user_id=user_id),
        "add_to_cart":        lambda a: add_to_cart(user_id=user_id, book_id=a["book_id"], quantity=a.get("quantity", 1)),
        "view_cart":          lambda a: view_cart(user_id=user_id),
        "remove_from_cart":   lambda a: remove_from_cart(user_id=user_id, book_id=a["book_id"]),
        "checkout":           lambda a: checkout(user_id=user_id),
    }
    if name in user_id_tools:
        if not user_id:
            return {"error": "Necesitas iniciar sesion para esta accion."}
        return user_id_tools[name](args)

    fn_map = {
        "search_books": search_books,
        "book_details": book_details,
        "top_books": top_books,
        "create_book": create_book,
        "delete_books_no_stock": delete_books_no_stock,
        "top_sold_month": top_sold_month,
        "low_stock_alert": low_stock_alert,
    }
    fn = fn_map[name]
    try:
        return fn(**args)
    except Exception as e:
        return {"error": f"Fallo al ejecutar {name}: {type(e).__name__}: {e}"}
