"""FastAPI HTTP server: GET /health, POST /chat."""
import logging
import os
from typing import Literal
from fastapi import FastAPI, HTTPException, Header
from pydantic import BaseModel, Field
from agent import chat

logging.basicConfig(level=logging.INFO)
log = logging.getLogger("hermes-agent")

app = FastAPI(title="Hermes Agent (PowerLetters)", version="1.0.0")

INTERNAL_TOKEN = os.environ.get("HERMES_INTERNAL_TOKEN", "")


class Message(BaseModel):
    role: Literal["user", "assistant"]
    content: str = Field(min_length=1, max_length=2000)


class ChatRequest(BaseModel):
    messages: list[Message] = Field(min_length=1, max_length=20)
    role: Literal["client", "admin"] = "client"
    user_id: int | None = None


class ChatResponse(BaseModel):
    reply: str
    artifacts: list[dict] = []
    tool_log: list[dict] = []


@app.get("/health")
def health():
    return {"status": "ok", "service": "hermes-agent"}


@app.post("/chat", response_model=ChatResponse)
async def chat_endpoint(
    req: ChatRequest,
    x_internal_token: str = Header(default=""),
):
    if INTERNAL_TOKEN and x_internal_token != INTERNAL_TOKEN:
        raise HTTPException(status_code=401, detail="Token interno invalido")

    msgs = [m.model_dump() for m in req.messages]
    try:
        result = await chat(msgs, role=req.role, user_id=req.user_id)
    except Exception as e:
        log.exception("Error en agent.chat")
        raise HTTPException(status_code=500, detail=f"Agent error: {type(e).__name__}: {e}")
    return result
