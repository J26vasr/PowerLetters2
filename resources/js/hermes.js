// Hermes widget. Renderiza artifacts (book grid, cart panel, order) y maneja acciones inline.
(async function () {
    'use strict';
    if (window.__hermesLoaded) return;
    window.__hermesLoaded = true;

    const CHAT_URL       = '/api/agent/chat.php';
    const SESSION_URL    = '/api/agent/session.php';
    const MARKED_CDN     = 'https://cdn.jsdelivr.net/npm/marked@14/marked.min.js';
    const DOMPURIFY_CDN  = 'https://cdn.jsdelivr.net/npm/dompurify@3.2.3/dist/purify.min.js';

    // Auth gate: sin sesion el widget no se monta.
    let session;
    try {
        session = await (await fetch(SESSION_URL, { credentials: 'same-origin' })).json();
    } catch {
        return;
    }
    if (!session.authenticated) return;

    // Storage key por usuario: cada cliente/admin tiene su propio historial.
    const STORAGE_KEY = `hermes_history_v2_${session.role}_${session.user_id}`;

    const SUGGESTIONS = [
        'Que me recomendas?',
        'Mostrame los mas vendidos',
        'Buscame algo de drama',
        'Ver mi carrito',
    ];
    // SVG icons (inline, sin dependencias)
    const SVG = {
        send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>',
        sparkle: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 L14 9 L21 11 L14 13 L12 20 L10 13 L3 11 L10 9 Z"/></svg>',
        close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
        trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>',
        cart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>',
        plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        eye: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
    };
    // Markup
    const root = document.createElement('div');
    root.innerHTML = `
        <button id="hermes-fab" title="Hermes" aria-label="Abrir Hermes">
            <span class="icon">${SVG.sparkle}</span>
            <span>Hermes</span>
        </button>
        <div id="hermes-panel" role="dialog" aria-label="Hermes Agent">
            <header id="hermes-header">
                <div id="hermes-brand">
                    <div id="hermes-mark">H</div>
                    <div>
                        <div class="name">Hermes</div>
                        <div class="meta">Agente · PowerLetters</div>
                    </div>
                </div>
                <button class="hermes-header-btn" id="hermes-clear" title="Limpiar">${SVG.trash}</button>
                <button class="hermes-header-btn" id="hermes-close" title="Cerrar">${SVG.close}</button>
            </header>
            <div id="hermes-messages"></div>
            <div id="hermes-suggestions" hidden></div>
            <div id="hermes-input-wrap">
                <div id="hermes-input-row">
                    <textarea id="hermes-input" rows="1"
                              placeholder="Preguntale algo a Hermes..."
                              autocomplete="off"></textarea>
                    <button id="hermes-send" disabled aria-label="Enviar">${SVG.send}</button>
                </div>
                <div id="hermes-hint"><kbd>Enter</kbd> para enviar  ·  <kbd>Shift</kbd>+<kbd>Enter</kbd> nueva linea</div>
            </div>
        </div>
    `;
    document.body.appendChild(root);

    const $fab     = document.getElementById('hermes-fab');
    const $panel   = document.getElementById('hermes-panel');
    const $close   = document.getElementById('hermes-close');
    const $clear   = document.getElementById('hermes-clear');
    const $msgs    = document.getElementById('hermes-messages');
    const $sugs    = document.getElementById('hermes-suggestions');
    const $input   = document.getElementById('hermes-input');
    const $send    = document.getElementById('hermes-send');
    // Libs CDN
    const loadScript = (url) => new Promise((ok, err) => {
        const s = document.createElement('script');
        s.src = url; s.onload = ok; s.onerror = err;
        document.head.appendChild(s);
    });
    const libsReady = Promise.all([loadScript(MARKED_CDN), loadScript(DOMPURIFY_CDN)])
        .then(() => window.marked?.setOptions?.({ breaks: true, gfm: true }))
        .catch(() => console.warn('Hermes: CDN no cargo, fallback texto plano.'));

    function md(text) {
        if (window.marked && window.DOMPurify) {
            return window.DOMPurify.sanitize(window.marked.parse(text), {
                ALLOWED_TAGS: ['p','strong','em','ul','ol','li','code','pre','a','br','del'],
                ALLOWED_ATTR: ['href','title','target','rel'],
            });
        }
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }
    const esc = (s) => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; };
    const fmt = (n) => `$${Number(n || 0).toFixed(2)}`;
    // Estado
    const state = {
        history: loadHistory(),
        userInitial: (session.username || 'U').charAt(0).toUpperCase(),
    };
    function loadHistory() {
        try { return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); }
        catch { return []; }
    }
    function saveHistory() {
        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state.history)); } catch {}
    }
    // Renderers
    function appendRow(role, opts = {}) {
        const row = document.createElement('div');
        row.className = 'hermes-row ' + role;
        $msgs.appendChild(row);
        $msgs.scrollTop = $msgs.scrollHeight;
        return row;
    }
    function appendMessage(role, content) {
        const row = appendRow(role);
        const bubble = document.createElement('div');
        bubble.className = 'hermes-bubble';
        if (role === 'assistant') {
            libsReady.then(() => { bubble.innerHTML = md(content); });
            bubble.textContent = content;
        } else {
            bubble.textContent = content;
        }
        row.appendChild(bubble);
        $msgs.scrollTop = $msgs.scrollHeight;
        return row;
    }
    function appendError(text) {
        const row = appendRow('error');
        const bubble = document.createElement('div');
        bubble.className = 'hermes-bubble';
        bubble.textContent = text;
        row.appendChild(bubble);
        $msgs.scrollTop = $msgs.scrollHeight;
    }
    function appendThinking(label = 'Hermes esta trabajando') {
        const row = appendRow('assistant');
        row.innerHTML = `
            <div class="hermes-thinking">
                <div class="dots"><span></span><span></span><span></span></div>
                <span>${esc(label)}</span>
            </div>`;
        $msgs.scrollTop = $msgs.scrollHeight;
        return row;
    }
    // Artifacts
    function renderArtifact(art) {
        const wrap = document.createElement('div');
        wrap.className = 'hermes-artifact';
        switch (art.type) {
            case 'book_grid':       wrap.innerHTML = bookGrid(art); break;
            case 'book_detail':     wrap.innerHTML = bookDetail(art.book); break;
            case 'cart_panel':      wrap.innerHTML = cartPanel(art); break;
            case 'order_confirmed': wrap.innerHTML = orderConfirmed(art); break;
            case 'orders_list':     wrap.innerHTML = ordersList(art); break;
            case 'low_stock':       wrap.innerHTML = lowStockList(art); break;
            default: return null;
        }
        return wrap;
    }

    function bookGrid(art) {
        const title = esc(art.title || 'Libros');
        const cards = art.books.map(bookCard).join('');
        return `
            <div class="hermes-artifact-title">${title} · ${art.books.length}</div>
            <div class="hermes-book-grid">${cards}</div>`;
    }

    function bookCard(b) {
        const id = Number(b.id_libro);
        const stockClass = b.existencias === 0 ? 'hermes-book__stock-out'
                         : b.existencias <= 5  ? 'hermes-book__stock-low' : '';
        const stockText = b.existencias === 0 ? 'Sin stock'
                       : `${b.existencias} disponibles`;
        const cover = b.imagen
            ? `<img class="hermes-book__cover" src="/api/images/libros/${esc(b.imagen)}"
                     onerror="this.classList.add('no-img');this.removeAttribute('src');this.innerText='?';" alt="">`
            : `<div class="hermes-book__cover no-img">?</div>`;
        return `
            <div class="hermes-book" data-book-id="${id}">
                ${cover}
                <div class="hermes-book__info">
                    <div class="hermes-book__title">${esc(b.titulo)}</div>
                    <div class="hermes-book__author">${esc(b.nombre_autor || '')}</div>
                    <div class="hermes-book__meta">
                        <span class="hermes-book__price">${fmt(b.precio)}</span>
                        <span class="${stockClass}">${stockText}</span>
                    </div>
                    <div class="hermes-book__actions">
                        <button class="hermes-btn" data-action="open" data-book-id="${id}">
                            ${SVG.eye}<span>Ver</span>
                        </button>
                        <button class="hermes-btn hermes-btn--primary" data-action="add" data-book-id="${id}"
                                ${b.existencias === 0 ? 'disabled' : ''}>
                            ${SVG.plus}<span>Agregar</span>
                        </button>
                    </div>
                </div>
            </div>`;
    }

    function bookDetail(b) {
        return `
            <div class="hermes-artifact-title">Detalle</div>
            <div class="hermes-book-grid">${bookCard(b)}</div>`;
    }

    function cartPanel(art) {
        if (!art.items.length) {
            return `
                <div class="hermes-cart">
                    <div class="hermes-cart__header">Carrito vacio</div>
                </div>`;
        }
        const items = art.items.map(it => `
            <div class="hermes-cart__item">
                <span class="hermes-cart__title">${esc(it.titulo)}</span>
                <span class="hermes-cart__qty">${esc(it.cantidad)} ×</span>
                <span class="hermes-cart__price">${fmt(it.subtotal)}</span>
            </div>`).join('');
        return `
            <div class="hermes-cart">
                <div class="hermes-cart__header">
                    Carrito actual
                    <span class="hermes-cart__count">${esc(art.item_count)} ${art.item_count === 1 ? 'item' : 'items'}</span>
                </div>
                <div class="hermes-cart__items">${items}</div>
                <div class="hermes-cart__footer">
                    <span class="hermes-cart__total-label">Total</span>
                    <span class="hermes-cart__total">${fmt(art.total)}</span>
                </div>
            </div>
            <div style="display:flex; gap:6px; margin-top:8px;">
                <button class="hermes-action" data-action="navigate" data-url="/views/public/carrito.html">
                    ${SVG.cart}<span>Abrir carrito</span>
                </button>
            </div>`;
    }

    function orderConfirmed(art) {
        return `
            <div class="hermes-order-confirmed">
                <div class="hermes-order-confirmed__title">
                    ${SVG.check}<span>Pedido #${esc(art.order_id)} confirmado</span>
                </div>
                <div class="hermes-order-confirmed__meta">
                    ${esc(art.items)} ${art.items === 1 ? 'item' : 'items'} · Total ${fmt(art.total)}
                </div>
                <div style="margin-top:10px;">
                    <button class="hermes-action" data-action="navigate" data-url="/views/public/historial_pedidos.html">
                        ${SVG.arrow}<span>Ver mis pedidos</span>
                    </button>
                </div>
            </div>`;
    }

    function ordersList(art) {
        const rows = art.orders.map(o => {
            const status = String(o.estado || '').toLowerCase();
            const date = o.fecha_pedido ? String(o.fecha_pedido).slice(0, 10) : '';
            return `
                <div class="hermes-order-row">
                    <span class="hermes-order-row__id">#${esc(o.id_pedido)}</span>
                    <span class="hermes-order-row__date">${esc(date)}</span>
                    <span class="hermes-order-row__status ${status}">${esc(o.estado)}</span>
                    <span class="hermes-order-row__total">${fmt(o.total)}</span>
                </div>`;
        }).join('');
        return `
            <div class="hermes-artifact-title">Tus pedidos · ${art.orders.length}</div>
            <div class="hermes-orders">${rows}</div>`;
    }

    function lowStockList(art) {
        const rows = art.books.map(b => `
            <div class="hermes-cart__item">
                <span class="hermes-cart__title">${esc(b.titulo)}</span>
                <span class="hermes-cart__qty">${esc(b.nombre_autor || '')}</span>
                <span class="hermes-cart__price ${b.existencias <= 3 ? 'hermes-book__stock-out' : 'hermes-book__stock-low'}">${esc(b.existencias)} u.</span>
            </div>`).join('');
        return `
            <div class="hermes-artifact-title">Stock bajo (≤ ${esc(art.threshold)})</div>
            <div class="hermes-cart">
                <div class="hermes-cart__items">${rows}</div>
            </div>`;
    }
    // Acciones de las cards (delegacion)
    $msgs.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-action], .hermes-action');
        if (!btn) return;
        const action = btn.dataset.action;
        const bookId = Number(btn.dataset.bookId);

        if (action === 'open' && bookId) {
            window.location.href = `/views/public/detalle_libro.html?id=${bookId}`;
        }
        else if (action === 'add' && bookId) {
            btn.disabled = true;
            await sendInternal(`Agregame al carrito el libro id ${bookId} (cantidad 1)`,
                               `Agregando libro ${bookId} al carrito`);
        }
        else if (action === 'navigate') {
            const url = btn.dataset.url;
            if (url) window.location.href = url;
        }
    });
    // Sugerencias
    function renderSuggestions() {
        if (state.history.length > 0) { $sugs.hidden = true; return; }
        $sugs.hidden = false;
        $sugs.innerHTML = '';
        SUGGESTIONS.forEach(q => {
            const b = document.createElement('button');
            b.className = 'hermes-chip';
            b.textContent = q;
            b.addEventListener('click', () => {
                $input.value = q;
                send();
            });
            $sugs.appendChild(b);
        });
    }
    function hideSuggestions() { $sugs.hidden = true; }
    // Hydrate
    function rehydrate() {
        $msgs.innerHTML = '';
        if (state.history.length === 0) {
            appendMessage('assistant',
                'Soy **Hermes**, agente de PowerLetters. Buscame un libro, pedime una recomendacion ' +
                'o decime "comprame X". Tambien puedo finalizar pedidos.'
            );
        } else {
            // Re-renderizar el historial. Cada turno guarda (role, content, artifacts).
            state.history.forEach(m => {
                const row = appendMessage(m.role, m.content);
                if (m.artifacts && m.role === 'assistant') {
                    m.artifacts.forEach(a => {
                        const el = renderArtifact(a);
                        if (el) row.appendChild(el);
                    });
                }
            });
        }
        renderSuggestions();
    }
    // Envio
    let busy = false;
    function setBusy(b) { busy = b; $send.disabled = b || !$input.value.trim(); }

    async function send() {
        const text = $input.value.trim();
        if (!text || busy) return;
        $input.value = '';
        autoResize();
        await sendInternal(text);
    }

    async function sendInternal(text, thinkingLabel) {
        hideSuggestions();
        appendMessage('user', text);
        // Para mandar al backend, NO incluimos los artifacts (son solo cliente)
        const historyForApi = state.history.map(m => ({ role: m.role, content: m.content }));
        historyForApi.push({ role: 'user', content: text });
        state.history.push({ role: 'user', content: text });
        saveHistory();

        setBusy(true);
        const $thinking = appendThinking(thinkingLabel || 'Hermes esta trabajando');

        try {
            const resp = await fetch(CHAT_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messages: historyForApi.slice(-20) }),
                credentials: 'same-origin',
            });

            $thinking.remove();

            if (resp.status === 401) {
                appendError('Para usar el asistente, necesitas iniciar sesion.');
                state.history.pop(); saveHistory();
                return;
            }
            const data = await resp.json();
            if (!resp.ok || data.error) {
                appendError(data.error || 'No pude procesar tu mensaje.');
                state.history.pop(); saveHistory();
                return;
            }

            const reply     = (data.reply || '').trim();
            const artifacts = data.artifacts || [];

            // Render del turno: mensaje + artifacts
            const row = appendRow('assistant');
            if (reply) {
                const bubble = document.createElement('div');
                bubble.className = 'hermes-bubble';
                libsReady.then(() => { bubble.innerHTML = md(reply); });
                bubble.textContent = reply;
                row.appendChild(bubble);
            }
            artifacts.forEach(a => {
                const el = renderArtifact(a);
                if (el) row.appendChild(el);
            });
            $msgs.scrollTop = $msgs.scrollHeight;

            state.history.push({
                role: 'assistant',
                content: reply,
                artifacts,
            });
            saveHistory();
        } catch (e) {
            $thinking.remove();
            appendError('Error de conexion: ' + e.message);
            state.history.pop(); saveHistory();
        } finally {
            setBusy(false);
            $input.focus();
        }
    }
    // Helpers de UI
    function autoResize() {
        $input.style.height = 'auto';
        $input.style.height = Math.min($input.scrollHeight, 100) + 'px';
    }

    let initialized = false;
    function openPanel() {
        $panel.classList.add('open');
        if (!initialized) { rehydrate(); initialized = true; }
        setTimeout(() => $input.focus(), 100);
    }
    function closePanel() { $panel.classList.remove('open'); }

    $fab.addEventListener('click',
        () => $panel.classList.contains('open') ? closePanel() : openPanel());
    $close.addEventListener('click', closePanel);

    $clear.addEventListener('click', () => {
        if (!state.history.length) return;
        if (!confirm('¿Limpiar toda la conversacion?')) return;
        state.history = [];
        saveHistory();
        rehydrate();
    });

    $input.addEventListener('input', () => {
        autoResize();
        $send.disabled = busy || !$input.value.trim();
    });
    $input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });
    $send.addEventListener('click', send);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && $panel.classList.contains('open')) closePanel();
    });
})();
