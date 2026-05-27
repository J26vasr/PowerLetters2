"""Cliente PyMySQL hacia MariaDB con helper de transaccion atomica."""
import os
from contextlib import contextmanager
import pymysql
from pymysql.cursors import DictCursor

_DB_CONFIG = {
    "host": os.environ["DB_HOST"],
    "port": int(os.environ.get("DB_PORT", 3306)),
    "user": os.environ["DB_USER"],
    "password": os.environ["DB_PASSWORD"],
    "database": os.environ["DB_NAME"],
    "charset": "utf8mb4",
    "cursorclass": DictCursor,
    "autocommit": True,
}


def get_conn():
    return pymysql.connect(**_DB_CONFIG)


def query(sql: str, params: tuple = ()) -> list[dict]:
    with get_conn() as conn, conn.cursor() as cur:
        cur.execute(sql, params)
        rows = cur.fetchall()
        return list(rows) if rows else []


def query_one(sql: str, params: tuple = ()) -> dict | None:
    rows = query(sql, params)
    return rows[0] if rows else None


def execute(sql: str, params: tuple = ()) -> int:
    with get_conn() as conn, conn.cursor() as cur:
        cur.execute(sql, params)
        return cur.rowcount or 0


@contextmanager
def transaction():
    """Context manager para operaciones multi-SQL atomicas."""
    conn = pymysql.connect(**{**_DB_CONFIG, "autocommit": False})
    try:
        cur = conn.cursor()

        class TxCtx:
            def execute(self_, sql, params=()):
                cur.execute(sql, params)
                return cur.rowcount or 0

            def query(self_, sql, params=()):
                cur.execute(sql, params)
                return list(cur.fetchall()) if cur.rowcount > 0 else []

            def query_one(self_, sql, params=()):
                rows = self_.query(sql, params)
                return rows[0] if rows else None

            @property
            def lastrowid(self_):
                return cur.lastrowid

        yield TxCtx()
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()
