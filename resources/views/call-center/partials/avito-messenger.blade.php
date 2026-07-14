@if(!$avitoConfigured)
    <div class="alert alert-warning">
        Avito не настроен. Укажите <strong>Client ID</strong>, <strong>Client Secret</strong> и <strong>user_id</strong> филиалов в
        <a href="{{ route('settings.system.index') }}">настройках</a>.
        Нужна подписка Avito «API мессенджера» для чтения и отправки сообщений.
    </div>
@endif

<div class="cc-tg-layout cc-avito-layout" id="ccAvitoLayout">
    <aside class="cc-tg-chats">
        <div class="cc-avito-branch-bar">
            <label class="form-label small text-muted mb-1">Филиал</label>
            <select class="form-select form-select-sm" id="ccAvitoBranch" {{ $avitoConfigured ? '' : 'disabled' }}>
                @foreach($avitoBranches as $b)
                    <option value="{{ $b['slug'] }}" {{ ($avitoDefaultBranch ?? 'kolhidskaya') === $b['slug'] ? 'selected' : '' }}
                        {{ $b['configured'] ? '' : 'disabled' }}>
                        {{ $b['label'] }}{{ $b['configured'] ? '' : ' (не настроен)' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="cc-tg-chats-header d-flex justify-content-between align-items-center">
            <span>Чаты по объявлениям</span>
            <button type="button" class="btn btn-sm btn-link p-0 d-md-none" id="ccAvitoBack" style="display:none">&larr; Назад</button>
        </div>
        <div class="cc-tg-chat-list" id="ccAvitoChatList">
            <p class="text-muted small p-3 mb-0">Выберите филиал — загрузим чаты с Avito.</p>
        </div>
    </aside>

    <section class="cc-tg-thread">
        <div class="cc-tg-thread-header" id="ccAvitoThreadTitle">Выберите чат</div>
        <div class="cc-tg-thread-sub text-muted small px-3 pb-2" id="ccAvitoThreadSub" style="display:none"></div>
        <div class="cc-tg-messages" id="ccAvitoMessages">
            <p class="text-muted small mb-0">Слева — чаты, сгруппированные по объявлениям. Выберите диалог, чтобы ответить покупателю.</p>
        </div>
        <form class="cc-tg-compose" id="ccAvitoCompose" style="display:none">
            <textarea class="form-control" id="ccAvitoInput" rows="1" maxlength="3900" placeholder="Сообщение…" {{ $avitoConfigured ? '' : 'disabled' }}></textarea>
            <button type="submit" class="btn btn-primary" id="ccAvitoSendBtn" {{ $avitoConfigured ? '' : 'disabled' }}>
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
        chats: @json(route('call-center.avito.chats')),
        messagesBase: @json(url('call-center/avito/chats')),
    };

    const layout = document.getElementById('ccAvitoLayout');
    const branchSel = document.getElementById('ccAvitoBranch');
    const chatList = document.getElementById('ccAvitoChatList');
    const messagesEl = document.getElementById('ccAvitoMessages');
    const titleEl = document.getElementById('ccAvitoThreadTitle');
    const subEl = document.getElementById('ccAvitoThreadSub');
    const compose = document.getElementById('ccAvitoCompose');
    const input = document.getElementById('ccAvitoInput');
    const sendBtn = document.getElementById('ccAvitoSendBtn');
    const backBtn = document.getElementById('ccAvitoBack');

    let activeChatId = null;
    let activeBranch = branchSel?.value || 'gorsky';
    let activeChatMeta = null;

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

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

    function renderListings(listings) {
        if (!listings || !listings.length) {
            chatList.innerHTML = '<p class="text-muted small p-3 mb-0">Нет чатов для этого филиала.</p>';
            return;
        }

        let html = '';
        listings.forEach(function (listing) {
            const itemTitle = listing.item_title || 'Объявление';
            const price = listing.item_price ? ' · ' + esc(listing.item_price) : '';
            html += '<div class="cc-avito-listing-group">';
            html += '<div class="cc-avito-listing-head">';
            if (listing.item_url) {
                html += '<a href="' + esc(listing.item_url) + '" target="_blank" rel="noopener" class="listing-title">' + esc(itemTitle) + '</a>';
            } else {
                html += '<span class="listing-title">' + esc(itemTitle) + '</span>';
            }
            html += '<span class="listing-meta">' + esc(price) + '</span>';
            html += '</div>';
            (listing.chats || []).forEach(function (chat) {
                html += '<button type="button" class="cc-tg-chat-item cc-avito-chat-item" data-chat-id="' + esc(chat.chat_id) + '"';
                html += ' data-title="' + esc(chat.title) + '" data-item="' + esc(itemTitle) + '"';
                html += ' data-item-url="' + esc(chat.item_url || '') + '">';
                html += '<div class="title">' + esc(chat.peer_name || chat.title) + (chat.unread ? ' <span class="badge bg-primary">new</span>' : '') + '</div>';
                html += '<div class="preview">' + esc(chat.last_message || '') + '</div>';
                html += '<div class="meta">' + esc(chat.last_at_human || '') + '</div>';
                html += '</button>';
            });
            html += '</div>';
        });
        chatList.innerHTML = html;
    }

    async function loadChats() {
        activeBranch = branchSel?.value || activeBranch;
        chatList.innerHTML = '<p class="text-muted small p-3 mb-0">Загрузка чатов…</p>';
        try {
            const url = urls.chats + '?branch=' + encodeURIComponent(activeBranch);
            const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await resp.json();
            if (!data.ok) throw new Error(data.error || 'Ошибка загрузки');
            renderListings(data.listings || []);
        } catch (e) {
            chatList.innerHTML = '<p class="text-danger small p-3 mb-0">' + esc(e.message || String(e)) + '</p>';
        }
    }

    async function loadChat(chatId, title, itemTitle, itemUrl) {
        activeChatId = chatId;
        activeChatMeta = { title: title, itemTitle: itemTitle, itemUrl: itemUrl };
        titleEl.textContent = title || chatId;
        if (itemTitle) {
            subEl.style.display = 'block';
            if (itemUrl) {
                subEl.innerHTML = 'Объявление: <a href="' + esc(itemUrl) + '" target="_blank" rel="noopener">' + esc(itemTitle) + '</a>';
            } else {
                subEl.textContent = 'Объявление: ' + itemTitle;
            }
        } else {
            subEl.style.display = 'none';
        }
        compose.style.display = 'flex';
        messagesEl.innerHTML = '<p class="text-muted small mb-0">Загрузка…</p>';
        if (layout) layout.classList.add('chat-open');
        if (backBtn) backBtn.style.display = 'inline-block';

        chatList.querySelectorAll('.cc-avito-chat-item').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.chatId === chatId);
        });

        try {
            const url = urls.messagesBase + '/' + encodeURIComponent(chatId) + '/messages?branch=' + encodeURIComponent(activeBranch);
            const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await resp.json();
            if (!data.ok && !data.messages) throw new Error(data.error || 'Ошибка загрузки');
            if (data.chat && data.chat.title) {
                titleEl.textContent = data.chat.peer_name || data.chat.title;
            }
            renderMessages(data.messages || []);
        } catch (e) {
            messagesEl.innerHTML = '<p class="text-danger small mb-0">' + esc(e.message || String(e)) + '</p>';
        }
    }

    branchSel?.addEventListener('change', function () {
        activeChatId = null;
        compose.style.display = 'none';
        titleEl.textContent = 'Выберите чат';
        subEl.style.display = 'none';
        messagesEl.innerHTML = '<p class="text-muted small mb-0">Слева — чаты по объявлениям выбранного филиала.</p>';
        loadChats();
    });

    chatList?.addEventListener('click', function (e) {
        const btn = e.target.closest('.cc-avito-chat-item');
        if (!btn) return;
        loadChat(btn.dataset.chatId, btn.dataset.title, btn.dataset.item, btn.dataset.itemUrl);
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
                body: JSON.stringify({ text: text, branch: activeBranch }),
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

    if (branchSel && !branchSel.disabled) {
        loadChats();
    }
})();
</script>
@endpush
