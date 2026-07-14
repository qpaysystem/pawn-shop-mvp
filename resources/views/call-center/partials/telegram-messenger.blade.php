@if(!$telegramConfigured)
    <div class="alert alert-warning">
        Telegram не настроен. Укажите <strong>telegram_bot_token</strong> в
        <a href="{{ route('settings.system.index') }}">настройках</a> и задайте группы inbox.
    </div>
@endif

<div class="cc-tg-layout" id="ccTgLayout">
    <aside class="cc-tg-chats">
        <div class="cc-tg-sidebar-tabs" role="tablist">
            <button type="button" class="cc-tg-sidebar-tab active" data-pane="chats" role="tab" aria-selected="true">
                <i class="bi bi-chat-left-text"></i> Чаты
            </button>
            <button type="button" class="cc-tg-sidebar-tab" data-pane="search" role="tab" aria-selected="false">
                <i class="bi bi-search"></i> Поиск
            </button>
        </div>

        <div class="cc-tg-pane" id="ccTgPaneChats">
            <div class="cc-tg-chats-header d-flex justify-content-between align-items-center">
                <span>Диалоги</span>
                <button type="button" class="btn btn-sm btn-link p-0 d-md-none" id="ccTgBack" style="display:none">&larr; Назад</button>
            </div>
            <div class="cc-tg-chat-list" id="ccTgChatList">
                @forelse($telegramChats as $chat)
                    <button type="button"
                            class="cc-tg-chat-item"
                            data-chat-id="{{ $chat['chat_id'] }}"
                            data-title="{{ $chat['title'] }}">
                        <div class="title">{{ $chat['title'] }}</div>
                        <div class="preview">{{ $chat['last_message'] ?: 'Нет сообщений' }}</div>
                        <div class="meta">{{ $chat['last_at_human'] ?: $chat['chat_id'] }}</div>
                    </button>
                @empty
                    <p class="text-muted small p-3 mb-0">Чатов пока нет. Сообщения появятся после входящих в Telegram или через поиск.</p>
                @endforelse
            </div>
        </div>

        <div class="cc-tg-pane" id="ccTgPaneSearch" hidden>
            <div class="cc-tg-search-box">
                <i class="bi bi-search text-muted"></i>
                <input type="search" class="form-control form-control-sm border-0 shadow-none" id="ccTgSearchInput"
                       placeholder="Клиент, телефон, @username, ID…" autocomplete="off"
                       {{ $telegramConfigured ? '' : 'disabled' }}>
            </div>
            <div class="cc-tg-search-hint text-muted small px-3 py-2">
                Клиенты CRM, известные чаты и новый диалог по @username (если пользователь писал боту).
            </div>
            <div class="cc-tg-search-results" id="ccTgSearchResults">
                <p class="text-muted small p-3 mb-0">Введите минимум 2 символа.</p>
            </div>
        </div>
    </aside>

    <section class="cc-tg-thread">
        <div class="cc-tg-thread-header" id="ccTgThreadTitle">Выберите чат</div>
        <div class="cc-tg-messages" id="ccTgMessages">
            <p class="text-muted small mb-0">Слева — чаты или поиск. Выберите диалог, чтобы просмотреть переписку и ответить.</p>
        </div>
        <form class="cc-tg-compose" id="ccTgCompose" style="display:none">
            <textarea class="form-control" id="ccTgInput" rows="1" maxlength="3900" placeholder="Сообщение…" {{ $telegramConfigured ? '' : 'disabled' }}></textarea>
            <button type="submit" class="btn btn-primary" id="ccTgSendBtn" {{ $telegramConfigured ? '' : 'disabled' }}>
                <i class="bi bi-send"></i>
            </button>
        </form>
    </section>
</div>

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const urls = {
        chats: @json(url('call-center/telegram/chats')),
        search: @json(route('call-center.telegram.search')),
        open: @json(route('call-center.telegram.open')),
        messagesBase: @json(url('call-center/telegram/chats')),
    };

    const layout = document.getElementById('ccTgLayout');
    const paneChats = document.getElementById('ccTgPaneChats');
    const paneSearch = document.getElementById('ccTgPaneSearch');
    const sidebarTabs = document.querySelectorAll('.cc-tg-sidebar-tab');
    const chatList = document.getElementById('ccTgChatList');
    const searchInput = document.getElementById('ccTgSearchInput');
    const searchResults = document.getElementById('ccTgSearchResults');
    const messagesEl = document.getElementById('ccTgMessages');
    const titleEl = document.getElementById('ccTgThreadTitle');
    const compose = document.getElementById('ccTgCompose');
    const input = document.getElementById('ccTgInput');
    const sendBtn = document.getElementById('ccTgSendBtn');
    const backBtn = document.getElementById('ccTgBack');
    let activeChatId = null;
    let searchTimer = null;

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function showPane(name) {
        const isSearch = name === 'search';
        paneChats.hidden = isSearch;
        paneSearch.hidden = !isSearch;
        sidebarTabs.forEach(function (tab) {
            const on = tab.dataset.pane === name;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (isSearch) {
            searchInput?.focus();
        }
    }

    sidebarTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            showPane(tab.dataset.pane || 'chats');
        });
    });

    function renderMessages(messages) {
        if (!messages.length) {
            messagesEl.innerHTML = '<p class="text-muted small mb-0">Сообщений пока нет.</p>';
            return;
        }
        messagesEl.innerHTML = messages.map(function (m) {
            const cls = m.outgoing ? 'out' : 'in';
            return '<div class="cc-tg-bubble ' + cls + '">' + esc(m.text) +
                '<span class="time">' + esc(m.sender) + ' · ' + esc(m.time) + '</span></div>';
        }).join('');
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendMessage(m) {
        if (messagesEl.querySelector('.text-muted')) {
            messagesEl.innerHTML = '';
        }
        const div = document.createElement('div');
        div.className = 'cc-tg-bubble ' + (m.outgoing ? 'out' : 'in');
        div.innerHTML = esc(m.text) + '<span class="time">' + esc(m.sender) + ' · ' + esc(m.time) + '</span>';
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function upsertChatListItem(chatId, title, preview) {
        let btn = chatList?.querySelector('.cc-tg-chat-item[data-chat-id="' + CSS.escape(chatId) + '"]');
        if (!btn && chatList) {
            const empty = chatList.querySelector('p.text-muted');
            if (empty) empty.remove();
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cc-tg-chat-item';
            btn.dataset.chatId = chatId;
            btn.dataset.title = title;
            btn.innerHTML = '<div class="title"></div><div class="preview"></div><div class="meta"></div>';
            chatList.prepend(btn);
            btn.addEventListener('click', function () {
                loadChat(btn.dataset.chatId, btn.dataset.title);
            });
        }
        if (btn) {
            btn.dataset.title = title;
            btn.querySelector('.title').textContent = title;
            if (preview) btn.querySelector('.preview').textContent = preview;
            btn.querySelector('.meta').textContent = chatId;
        }
    }

    async function loadChat(chatId, title) {
        activeChatId = chatId;
        titleEl.textContent = title || chatId;
        compose.style.display = 'flex';
        messagesEl.innerHTML = '<p class="text-muted small mb-0">Загрузка…</p>';
        if (layout) layout.classList.add('chat-open');
        if (backBtn) backBtn.style.display = 'inline-block';

        chatList?.querySelectorAll('.cc-tg-chat-item').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.chatId === chatId);
        });

        try {
            const url = urls.messagesBase + '/' + encodeURIComponent(chatId) + '/messages';
            const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await resp.json();
            if (!data.ok && !data.messages) throw new Error(data.error || 'Ошибка загрузки');
            const chatTitle = (data.chat && data.chat.title) || title || chatId;
            titleEl.textContent = chatTitle;
            activeChatId = (data.chat && data.chat.chat_id) || chatId;
            upsertChatListItem(activeChatId, chatTitle, '');
            renderMessages(data.messages || []);
        } catch (e) {
            messagesEl.innerHTML = '<p class="text-danger small mb-0">' + esc(e.message || String(e)) + '</p>';
        }
    }

    async function openAndLoad(opts) {
        const body = {};
        if (opts.target) body.target = opts.target;
        if (opts.clientId) body.client_id = opts.clientId;

        const resp = await fetch(urls.open, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await resp.json();
        if (!data.ok || !data.chat) {
            throw new Error(data.error || 'Не удалось открыть чат');
        }
        showPane('chats');
        await loadChat(data.chat.chat_id, data.chat.title);
    }

    async function handleSearchItem(item) {
        if (item.kind === 'client' && !item.can_open) {
            if (item.client_url) window.open(item.client_url, '_blank');
            return;
        }
        if (item.kind === 'client' && item.client_id) {
            await openAndLoad({ clientId: item.client_id });
            return;
        }
        if (item.kind === 'new_dialog') {
            await openAndLoad({ target: item.target });
            return;
        }
        const chatId = item.chat_id;
        if (!chatId) return;
        if (String(chatId).startsWith('@')) {
            await openAndLoad({ target: chatId });
            return;
        }
        showPane('chats');
        await loadChat(chatId, item.title);
    }

    function renderSearchResults(data) {
        const sections = data.sections || [];
        if (!sections.length) {
            searchResults.innerHTML = '<p class="text-muted small p-3 mb-0">Ничего не найдено.</p>';
            return;
        }
        let html = '';
        sections.forEach(function (section) {
            html += '<div class="cc-tg-search-section"><div class="cc-tg-search-section-title">' + esc(section.title) + '</div>';
            (section.items || []).forEach(function (item, idx) {
                const disabled = item.kind === 'client' && !item.can_open;
                html += '<button type="button" class="cc-tg-search-item' + (disabled ? ' is-muted' : '') + '" data-section="' + esc(section.key) + '" data-index="' + idx + '">';
                html += '<div class="title">' + esc(item.title) + '</div>';
                if (item.subtitle) html += '<div class="preview">' + esc(item.subtitle) + '</div>';
                if (disabled) html += '<div class="meta">Нет Telegram — откроется карточка клиента</div>';
                html += '</button>';
            });
            html += '</div>';
        });
        searchResults.innerHTML = html;
        searchResults._lastData = data;

        searchResults.querySelectorAll('.cc-tg-search-item').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const sectionKey = btn.dataset.section;
                const index = parseInt(btn.dataset.index, 10);
                const section = (searchResults._lastData.sections || []).find(function (s) { return s.key === sectionKey; });
                const item = section && section.items ? section.items[index] : null;
                if (!item) return;
                btn.disabled = true;
                try {
                    await handleSearchItem(item);
                } catch (e) {
                    alert(e.message || String(e));
                } finally {
                    btn.disabled = false;
                }
            });
        });
    }

    async function runSearch(q) {
        if (q.length < 2) {
            searchResults.innerHTML = '<p class="text-muted small p-3 mb-0">Введите минимум 2 символа.</p>';
            return;
        }
        searchResults.innerHTML = '<p class="text-muted small p-3 mb-0">Поиск…</p>';
        try {
            const url = urls.search + '?q=' + encodeURIComponent(q);
            const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await resp.json();
            renderSearchResults(data);
        } catch (e) {
            searchResults.innerHTML = '<p class="text-danger small p-3 mb-0">' + esc(e.message || String(e)) + '</p>';
        }
    }

    searchInput?.addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = (searchInput.value || '').trim();
        searchTimer = setTimeout(function () { runSearch(q); }, 280);
    });

    chatList?.addEventListener('click', function (e) {
        const btn = e.target.closest('.cc-tg-chat-item');
        if (!btn) return;
        loadChat(btn.dataset.chatId, btn.dataset.title);
    });

    backBtn?.addEventListener('click', function () {
        if (layout) layout.classList.remove('chat-open');
        backBtn.style.display = 'none';
    });

    compose?.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!activeChatId) return;
        const text = (input.value || '').trim();
        if (!text) return;
        sendBtn.disabled = true;
        try {
            const url = urls.messagesBase + '/' + encodeURIComponent(activeChatId) + '/messages';
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ text: text }),
            });
            const data = await resp.json();
            if (!data.ok) throw new Error(data.error || 'Не отправлено');
            input.value = '';
            if (data.message) appendMessage(data.message);
        } catch (err) {
            alert(err.message || String(err));
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    });

    input?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            compose.requestSubmit();
        }
    });

    const first = chatList?.querySelector('.cc-tg-chat-item');
    if (first) {
        loadChat(first.dataset.chatId, first.dataset.title);
    }
})();
</script>
@endpush
