// AI CRM Assistant — Floating chat panel
(function() {
    const html = `
<div id="aiPanel" style="position:fixed;bottom:80px;right:20px;width:380px;max-height:560px;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.22);display:none;flex-direction:column;z-index:9999;font-family:inherit;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#1a6bb5,#4f46e5);color:#fff;padding:13px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
    <div style="display:flex;align-items:center;gap:9px;">
      <span style="font-size:22px;">🤖</span>
      <div>
        <div style="font-weight:600;font-size:14px;">CRM AI Assistant</div>
        <div style="font-size:11px;opacity:.75;">Tra cứu & vận hành ticket thời gian thực</div>
      </div>
    </div>
    <button onclick="toggleAI()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;padding:2px 6px;opacity:.8;">✕</button>
  </div>
  <div id="aiMessages" style="flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;min-height:220px;max-height:360px;background:#f8fafc;">
    <div class="ai-msg-bot" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 13px;font-size:13px;max-width:92%;align-self:flex-start;line-height:1.55;box-shadow:0 1px 3px rgba(0,0,0,.06);">
      Xin chào! Tôi là trợ lý AI vận hành CRM. Tôi có thể giúp bạn:<br>
      • Xem thống kê ticket<br>
      • Tìm ticket theo tên khách hàng<br>
      • Kiểm tra ticket quá hạn<br>
      • Phân tích và gợi ý ưu tiên xử lý 📊
    </div>
  </div>
  <div style="padding:8px 10px 10px;border-top:1px solid #e5e7eb;background:#fff;flex-shrink:0;">
    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;">
      <button onclick="aiQuick('Thống kê ticket hiện tại')"         class="ai-chip">📊 Thống kê</button>
      <button onclick="aiQuick('Danh sách ticket quá hạn')"         class="ai-chip">⚠️ Quá hạn</button>
      <button onclick="aiQuick('Ticket chưa được phân công')"       class="ai-chip">👤 Chưa phân công</button>
      <button onclick="aiQuick('5 ticket mới nhất')"                class="ai-chip">🆕 Ticket mới</button>
    </div>
    <div style="display:flex;gap:6px;">
      <input id="aiInput" type="text" placeholder="Hỏi về ticket, khách hàng, thống kê..."
             style="flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px 11px;font-size:13px;outline:none;transition:border .2s;"
             onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#d1d5db'"
             onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAI();}">
      <button onclick="sendAI()" id="aiSendBtn"
              style="background:linear-gradient(135deg,#1a6bb5,#4f46e5);color:#fff;border:none;border-radius:8px;padding:8px 15px;cursor:pointer;font-size:13px;font-weight:500;white-space:nowrap;">Gửi</button>
    </div>
  </div>
</div>
<button id="aiFab" onclick="toggleAI()"
        style="position:fixed;bottom:20px;right:20px;width:54px;height:54px;background:linear-gradient(135deg,#1a6bb5,#4f46e5);color:#fff;border:none;border-radius:50%;font-size:24px;cursor:pointer;box-shadow:0 4px 18px rgba(79,70,229,.45);z-index:9999;display:flex;align-items:center;justify-content:center;transition:transform .15s;"
        onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'"
        title="CRM AI Assistant">🤖</button>
<style>
.ai-chip{font-size:11px;padding:3px 9px;border:1px solid #c7d2fe;color:#4338ca;background:#eef2ff;border-radius:20px;cursor:pointer;transition:background .15s;}
.ai-chip:hover{background:#e0e7ff;}
</style>`;

    document.addEventListener('DOMContentLoaded', function() {
        const div = document.createElement('div');
        div.innerHTML = html;
        document.body.appendChild(div);
    });

    window.toggleAI = function() {
        const panel = document.getElementById('aiPanel');
        if (!panel) return;
        const isOpen = panel.style.display === 'flex';
        panel.style.display = isOpen ? 'none' : 'flex';
        if (!isOpen) setTimeout(() => document.getElementById('aiInput')?.focus(), 120);
    };

    window.aiQuick = function(text) {
        const input = document.getElementById('aiInput');
        if (input) { input.value = text; sendAI(); }
    };

    // Render markdown-lite: **bold**, bullet lines, ticket IDs as links
    function renderText(text) {
        const div = document.createElement('div');
        // Escape HTML first
        let safe = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        // Bold
        safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Ticket ID links: #123 or ID: 123
        safe = safe.replace(/(?:^|\s)#(\d+)/g, (m, id) => ` <a href="tickets.php?action=view&id=${id}" target="_blank" style="color:#4f46e5;font-weight:600;">#${id}</a>`);
        // Newlines to <br>
        safe = safe.replace(/\n/g, '<br>');
        div.innerHTML = safe;
        return div;
    }

    function addMsg(text, isBot) {
        const msgs = document.getElementById('aiMessages');
        if (!msgs) return;
        const wrap = document.createElement('div');
        if (isBot) {
            wrap.style.cssText = 'background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 13px;font-size:13px;max-width:92%;align-self:flex-start;line-height:1.55;box-shadow:0 1px 3px rgba(0,0,0,.06);';
            wrap.appendChild(renderText(text));
        } else {
            wrap.style.cssText = 'background:linear-gradient(135deg,#1a6bb5,#4f46e5);color:#fff;border-radius:12px;padding:10px 13px;font-size:13px;max-width:88%;align-self:flex-end;line-height:1.5;word-break:break-word;';
            wrap.textContent = text;
        }
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;
        return wrap;
    }

    function addTyping() {
        const msgs = document.getElementById('aiMessages');
        const div = document.createElement('div');
        div.id = 'aiTyping';
        div.style.cssText = 'background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 13px;font-size:13px;max-width:60%;align-self:flex-start;color:#64748b;';
        div.innerHTML = '<span style="display:inline-flex;gap:4px;"><span class="dot" style="width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:aiDot 1.2s infinite;display:inline-block;"></span><span class="dot" style="width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:aiDot 1.2s .4s infinite;display:inline-block;"></span><span class="dot" style="width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:aiDot 1.2s .8s infinite;display:inline-block;"></span></span>';
        if (!document.getElementById('aiDotStyle')) {
            const s = document.createElement('style');
            s.id = 'aiDotStyle';
            s.textContent = '@keyframes aiDot{0%,100%{opacity:.2;transform:translateY(0)}50%{opacity:1;transform:translateY(-3px)}}';
            document.head.appendChild(s);
        }
        msgs?.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function removeTyping() {
        document.getElementById('aiTyping')?.remove();
    }

    window.sendAI = function() {
        const input = document.getElementById('aiInput');
        const btn   = document.getElementById('aiSendBtn');
        const text  = input?.value?.trim();
        if (!text) return;

        addMsg(text, false);
        input.value = '';
        btn.disabled = true;
        btn.textContent = '...';
        addTyping();

        const aiUrl = (window.APP_BASE || '') + 'api/ai.php';
        fetch(aiUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'crm_agent', content: text})
        })
        .then(r => r.text())
        .then(raw => {
            removeTyping();
            btn.disabled = false;
            btn.textContent = 'Gửi';
            try {
                const d = JSON.parse(raw);
                if (d.success) addMsg(d.reply, true);
                else addMsg('⚠️ ' + (d.message || 'Lỗi không xác định'), true);
            } catch(e) {
                addMsg('⚠️ Lỗi server: ' + raw.substring(0, 200), true);
            }
        })
        .catch(err => {
            removeTyping();
            btn.disabled = false;
            btn.textContent = 'Gửi';
            addMsg('⚠️ Lỗi kết nối: ' + err.message, true);
        });
    };

    // Called from ticket detail page — phân tích ticket cụ thể
    window.analyzeTicketWithAI = function(ticketData) {
        const panel = document.getElementById('aiPanel');
        if (panel) panel.style.display = 'flex';
        addTyping();

        const aiUrl = (window.APP_BASE || '') + 'api/ai.php';
        fetch(aiUrl, {
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
        .catch(err => { removeTyping(); addMsg('⚠️ Lỗi kết nối: ' + err.message, true); });
    };
})();
