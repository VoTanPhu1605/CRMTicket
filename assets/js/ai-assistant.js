// CRM AI Agent — Floating assistant panel
(function() {
    // ── Conversation history (persists within browser session) ──────────────
    let history = [];

    // ── Inject HTML ─────────────────────────────────────────────────────────
    const html = `
<div id="aiPanel" style="position:fixed;bottom:80px;right:20px;width:400px;max-height:600px;background:#fff;border-radius:18px;box-shadow:0 12px 40px rgba(0,0,0,.22);display:none;flex-direction:column;z-index:9999;font-family:inherit;overflow:hidden;">

  <!-- Header -->
  <div style="background:linear-gradient(135deg,#1a6bb5 0%,#4f46e5 100%);color:#fff;padding:13px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;">🤖</div>
      <div>
        <div style="font-weight:700;font-size:14px;letter-spacing:-.01em;">CRM AI Agent</div>
        <div style="font-size:11px;opacity:.75;margin-top:1px;"><span id="aiStatusDot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#4ade80;margin-right:4px;"></span>Sẵn sàng · Dữ liệu thời gian thực</div>
      </div>
    </div>
    <div style="display:flex;gap:6px;align-items:center;">
      <button onclick="clearAIHistory()" title="Xóa lịch sử" style="background:rgba(255,255,255,.15);border:none;color:#fff;font-size:12px;cursor:pointer;padding:4px 8px;border-radius:6px;opacity:.8;">🗑</button>
      <button onclick="toggleAI()" style="background:rgba(255,255,255,.15);border:none;color:#fff;font-size:16px;cursor:pointer;padding:4px 8px;border-radius:6px;">✕</button>
    </div>
  </div>

  <!-- Messages -->
  <div id="aiMessages" style="flex:1;overflow-y:auto;padding:14px 12px;display:flex;flex-direction:column;gap:10px;background:#f8fafc;min-height:240px;max-height:380px;">
    <div class="ai-bot-msg">
      Xin chào! Tôi là <strong>CRM AI Agent</strong> — trợ lý thông minh có thể can thiệp sâu vào hệ thống.<br><br>
      Tôi có thể giúp bạn:<br>
      📋 Xem chi tiết bất kỳ ticket (hỏi "ticket #5")<br>
      👥 Đề xuất phân công nhân viên phù hợp<br>
      🔍 Tìm khách hàng và lịch sử của họ<br>
      📊 Thống kê, hiệu suất nhân viên<br>
      💡 Gợi ý giải pháp từ ticket tương tự<br>
      ⚠️ Cảnh báo quá hạn, bảo hành sắp hết
    </div>
  </div>

  <!-- Quick chips -->
  <div style="padding:8px 10px 0;background:#fff;border-top:1px solid #f1f5f9;flex-shrink:0;">
    <div id="aiChips" style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;">
      <button onclick="aiQuick('Thống kê ticket hiện tại')"              class="ai-chip">📊 Thống kê</button>
      <button onclick="aiQuick('Danh sách ticket cần xử lý gấp nhất')"  class="ai-chip">🚨 Ưu tiên</button>
      <button onclick="aiQuick('Ticket quá hạn')"                        class="ai-chip">⚠️ Quá hạn</button>
      <button onclick="aiQuick('Ticket chưa phân công')"                 class="ai-chip">👤 Chưa PC</button>
      <button onclick="aiQuick('Báo cáo hiệu suất nhân viên')"          class="ai-chip">🏆 Hiệu suất</button>
      <button onclick="aiQuick('Bảo hành sắp hết hạn 30 ngày tới')"    class="ai-chip">🛡 Bảo hành</button>
      <button onclick="aiQuick('Doanh thu tổng hợp')"                    class="ai-chip">💰 Doanh thu</button>
      <button onclick="aiQuick('5 ticket mới nhất')"                     class="ai-chip">🆕 Mới nhất</button>
    </div>

    <!-- Input -->
    <div style="display:flex;gap:6px;padding-bottom:10px;">
      <input id="aiInput" type="text"
             placeholder='Hỏi tự do: "ticket #12", "phân công ticket #5", "tìm KH Nguyễn Văn A"...'
             style="flex:1;border:1.5px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;outline:none;transition:border .2s;background:#f8fafc;"
             onfocus="this.style.borderColor='#4f46e5';this.style.background='#fff'"
             onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc'"
             onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAI();}">
      <button onclick="sendAI()" id="aiSendBtn"
              style="background:linear-gradient(135deg,#1a6bb5,#4f46e5);color:#fff;border:none;border-radius:10px;padding:8px 16px;cursor:pointer;font-size:13px;font-weight:600;white-space:nowrap;transition:opacity .15s;"
              onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">Gửi</button>
    </div>
  </div>
</div>

<!-- FAB button -->
<button id="aiFab" onclick="toggleAI()"
        style="position:fixed;bottom:20px;right:20px;width:56px;height:56px;background:linear-gradient(135deg,#1a6bb5,#4f46e5);color:#fff;border:none;border-radius:50%;font-size:26px;cursor:pointer;box-shadow:0 6px 20px rgba(79,70,229,.5);z-index:9999;display:flex;align-items:center;justify-content:center;transition:transform .15s,box-shadow .15s;"
        onmouseover="this.style.transform='scale(1.1)';this.style.boxShadow='0 8px 26px rgba(79,70,229,.6)'"
        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 6px 20px rgba(79,70,229,.5)'"
        title="CRM AI Agent">🤖</button>

<style>
.ai-chip{font-size:11.5px;padding:4px 10px;border:1.5px solid #c7d2fe;color:#4338ca;background:#eef2ff;border-radius:20px;cursor:pointer;transition:all .15s;font-weight:500;}
.ai-chip:hover{background:#4f46e5;color:#fff;border-color:#4f46e5;}
.ai-bot-msg{background:#fff;border:1px solid #e2e8f0;border-radius:14px 14px 14px 2px;padding:11px 14px;font-size:13px;max-width:94%;align-self:flex-start;line-height:1.6;box-shadow:0 1px 4px rgba(0,0,0,.07);}
.ai-user-msg{background:linear-gradient(135deg,#1a6bb5,#4f46e5);color:#fff;border-radius:14px 14px 2px 14px;padding:10px 14px;font-size:13px;max-width:88%;align-self:flex-end;line-height:1.5;word-break:break-word;}
@keyframes aiDot{0%,100%{opacity:.25;transform:translateY(0)}50%{opacity:1;transform:translateY(-4px)}}
</style>`;

    document.addEventListener('DOMContentLoaded', function() {
        const div = document.createElement('div');
        div.innerHTML = html;
        document.body.appendChild(div);
    });

    // ── Toggle panel ────────────────────────────────────────────────────────
    window.toggleAI = function() {
        const panel = document.getElementById('aiPanel');
        if (!panel) return;
        const opening = panel.style.display !== 'flex';
        panel.style.display = opening ? 'flex' : 'none';
        if (opening) setTimeout(() => document.getElementById('aiInput')?.focus(), 120);
    };

    window.clearAIHistory = function() {
        history = [];
        const msgs = document.getElementById('aiMessages');
        if (msgs) msgs.innerHTML = '<div class="ai-bot-msg">Lịch sử đã xóa. Bắt đầu cuộc trò chuyện mới! 🆕</div>';
    };

    window.aiQuick = function(text) {
        const input = document.getElementById('aiInput');
        if (input) { input.value = text; sendAI(); }
    };

    // ── Render markdown-lite ─────────────────────────────────────────────────
    function renderMarkdown(text) {
        const div = document.createElement('div');
        let s = text
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            // Bold **text**
            .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
            // Italic *text*
            .replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g,'<em>$1</em>')
            // Ticket links #123
            .replace(/#(\d+)/g,'<a href="tickets.php?action=view&id=$1" target="_blank" style="color:#4f46e5;font-weight:700;text-decoration:none;">#$1</a>')
            // Headers ### and ##
            .replace(/^### (.+)$/gm,'<div style="font-weight:700;font-size:13px;margin:8px 0 4px;color:#1e293b;">$1</div>')
            .replace(/^## (.+)$/gm,'<div style="font-weight:700;font-size:14px;margin:10px 0 4px;color:#1a6bb5;border-bottom:1px solid #e2e8f0;padding-bottom:3px;">$1</div>')
            // Bullet lines starting with - or •
            .replace(/^[-•]\s+(.+)$/gm,'<div style="padding:1px 0 1px 14px;position:relative;"><span style="position:absolute;left:2px;color:#4f46e5;">•</span>$1</div>')
            // Numbered list
            .replace(/^(\d+)\.\s+(.+)$/gm,'<div style="padding:1px 0 1px 18px;position:relative;"><span style="position:absolute;left:2px;color:#64748b;font-size:11px;">$1.</span>$2</div>')
            // Inline code `code`
            .replace(/`([^`]+)`/g,'<code style="background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:12px;color:#e11d48;">$1</code>')
            // Newlines
            .replace(/\n\n/g,'<div style="height:6px;"></div>')
            .replace(/\n/g,'<br>');
        div.innerHTML = s;
        return div;
    }

    // ── Add message ──────────────────────────────────────────────────────────
    function addMsg(text, isBot) {
        const msgs = document.getElementById('aiMessages');
        if (!msgs) return;
        const wrap = document.createElement('div');
        if (isBot) {
            wrap.className = 'ai-bot-msg';
            wrap.appendChild(renderMarkdown(text));
        } else {
            wrap.className = 'ai-user-msg';
            wrap.textContent = text;
        }
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;
    }

    // ── Typing indicator ─────────────────────────────────────────────────────
    function addTyping() {
        const msgs = document.getElementById('aiMessages');
        if (!msgs || document.getElementById('aiTyping')) return;
        const d = document.createElement('div');
        d.id = 'aiTyping';
        d.className = 'ai-bot-msg';
        d.style.cssText = 'padding:12px 16px;';
        d.innerHTML = '<span style="display:inline-flex;gap:5px;align-items:center;">' +
            '<span style="font-size:11px;color:#94a3b8;margin-right:4px;">AI đang tra cứu dữ liệu...</span>' +
            '<span style="width:7px;height:7px;border-radius:50%;background:#4f46e5;animation:aiDot 1.2s infinite;display:inline-block;"></span>' +
            '<span style="width:7px;height:7px;border-radius:50%;background:#4f46e5;animation:aiDot 1.2s .4s infinite;display:inline-block;"></span>' +
            '<span style="width:7px;height:7px;border-radius:50%;background:#4f46e5;animation:aiDot 1.2s .8s infinite;display:inline-block;"></span>' +
            '</span>';
        msgs.appendChild(d);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function removeTyping() { document.getElementById('aiTyping')?.remove(); }

    // ── Send message ─────────────────────────────────────────────────────────
    window.sendAI = function() {
        const input = document.getElementById('aiInput');
        const btn   = document.getElementById('aiSendBtn');
        const text  = input?.value?.trim();
        if (!text || btn.disabled) return;

        addMsg(text, false);
        input.value = '';
        btn.disabled = true;
        btn.style.opacity = '.5';
        addTyping();

        const statusDot = document.getElementById('aiStatusDot');
        if (statusDot) { statusDot.style.background = '#f59e0b'; }

        // Append to history
        history.push({role: 'user', content: text});

        fetch((window.APP_BASE || '') + 'api/ai.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'crm_agent', content: text, history: history.slice(-10)})
        })
        .then(r => r.text())
        .then(raw => {
            removeTyping();
            btn.disabled = false;
            btn.style.opacity = '1';
            if (statusDot) statusDot.style.background = '#4ade80';
            try {
                const d = JSON.parse(raw);
                const reply = d.success ? d.reply : ('⚠️ ' + (d.message || 'Lỗi không xác định'));
                addMsg(reply, true);
                if (d.success) history.push({role: 'assistant', content: reply});
            } catch(e) {
                addMsg('⚠️ Lỗi server: ' + raw.substring(0, 300), true);
            }
        })
        .catch(err => {
            removeTyping();
            btn.disabled = false;
            btn.style.opacity = '1';
            if (statusDot) statusDot.style.background = '#ef4444';
            addMsg('⚠️ Lỗi kết nối: ' + err.message, true);
        });
    };

    // ── Ticket analysis (called from ticket detail page) ─────────────────────
    window.analyzeTicketWithAI = function(ticketData) {
        const panel = document.getElementById('aiPanel');
        if (panel) panel.style.display = 'flex';
        addTyping();

        fetch((window.APP_BASE || '') + 'api/ai.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'analyze_ticket', content: '', context: ticketData})
        })
        .then(r => r.text())
        .then(raw => {
            removeTyping();
            try {
                const d = JSON.parse(raw);
                addMsg(d.success ? d.reply : '⚠️ ' + d.message, true);
            } catch(e) {
                addMsg('⚠️ Lỗi server: ' + raw.substring(0, 150), true);
            }
        })
        .catch(err => { removeTyping(); addMsg('⚠️ Lỗi: ' + err.message, true); });
    };
})();
