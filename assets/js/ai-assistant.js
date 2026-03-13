// AI Assistant - Floating chat panel
(function() {
    // Inject HTML
    const html = `
<div id="aiPanel" style="position:fixed;bottom:80px;right:20px;width:360px;max-height:520px;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.18);display:none;flex-direction:column;z-index:9999;font-family:inherit;">
  <div style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;padding:14px 16px;border-radius:16px 16px 0 0;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:8px;">
      <span style="font-size:20px;">🤖</span>
      <div>
        <div style="font-weight:600;font-size:14px;">IT AI Assistant</div>
        <div style="font-size:11px;opacity:.8;">Powered by Claude</div>
      </div>
    </div>
    <button onclick="toggleAI()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:2px 6px;">✕</button>
  </div>
  <div id="aiMessages" style="flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;min-height:200px;max-height:340px;">
    <div class="ai-msg ai-bot" style="background:#f3f4f6;border-radius:12px;padding:10px 12px;font-size:13px;max-width:90%;align-self:flex-start;">
      Xin chào! Tôi là trợ lý AI cho hệ thống HelpDesk. Tôi có thể giúp bạn phân tích ticket, đề xuất giải pháp IT, hoặc trả lời câu hỏi kỹ thuật. 💡
    </div>
  </div>
  <div style="padding:8px 12px;border-top:1px solid #e5e7eb;">
    <div style="display:flex;gap-4px;flex-wrap:wrap;gap:4px;margin-bottom:8px;">
      <button onclick="aiQuick('Hướng dẫn reset mật khẩu Windows')" style="font-size:11px;padding:3px 8px;border:1px solid #4f46e5;color:#4f46e5;background:#fff;border-radius:20px;cursor:pointer;">Reset mật khẩu</button>
      <button onclick="aiQuick('Khắc phục lỗi kết nối mạng')" style="font-size:11px;padding:3px 8px;border:1px solid #4f46e5;color:#4f46e5;background:#fff;border-radius:20px;cursor:pointer;">Lỗi mạng</button>
      <button onclick="aiQuick('Máy tính chạy chậm phải làm gì')" style="font-size:11px;padding:3px 8px;border:1px solid #4f46e5;color:#4f46e5;background:#fff;border-radius:20px;cursor:pointer;">Máy chậm</button>
    </div>
    <div style="display:flex;gap:6px;">
      <input id="aiInput" type="text" placeholder="Nhập câu hỏi IT..."
             style="flex:1;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;font-size:13px;outline:none;"
             onkeydown="if(event.key==='Enter')sendAI()">
      <button onclick="sendAI()" id="aiSendBtn"
              style="background:#4f46e5;color:#fff;border:none;border-radius:8px;padding:7px 14px;cursor:pointer;font-size:13px;">Gửi</button>
    </div>
  </div>
</div>
<button id="aiFab" onclick="toggleAI()"
        style="position:fixed;bottom:20px;right:20px;width:52px;height:52px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:50%;font-size:22px;cursor:pointer;box-shadow:0 4px 16px rgba(79,70,229,.5);z-index:9999;display:flex;align-items:center;justify-content:center;"
        title="AI Assistant">🤖</button>`;

    document.addEventListener('DOMContentLoaded', function() {
        const div = document.createElement('div');
        div.innerHTML = html;
        document.body.appendChild(div);
    });

    window.toggleAI = function() {
        const panel = document.getElementById('aiPanel');
        if (!panel) return;
        panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
        if (panel.style.display === 'flex') {
            setTimeout(() => document.getElementById('aiInput')?.focus(), 100);
        }
    };

    window.aiQuick = function(text) {
        const input = document.getElementById('aiInput');
        if (input) { input.value = text; sendAI(); }
    };

    function addMsg(text, isBot) {
        const msgs = document.getElementById('aiMessages');
        if (!msgs) return;
        const div = document.createElement('div');
        div.style.cssText = `background:${isBot ? '#f3f4f6' : '#4f46e5'};color:${isBot ? '#111' : '#fff'};border-radius:12px;padding:10px 12px;font-size:13px;max-width:90%;align-self:${isBot ? 'flex-start' : 'flex-end'};white-space:pre-wrap;word-break:break-word;`;
        div.textContent = text;
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    window.sendAI = function() {
        const input  = document.getElementById('aiInput');
        const btn    = document.getElementById('aiSendBtn');
        const text   = input?.value?.trim();
        if (!text) return;

        addMsg(text, false);
        input.value = '';
        btn.disabled = true;
        btn.textContent = '...';

        // Gather ticket context if on ticket detail page
        const context = {};
        const titleEl = document.querySelector('h5.mb-0');
        if (titleEl) context.title = titleEl.textContent.trim();

        const aiUrl = (window.APP_BASE || '') + 'api/ai.php';
        fetch(aiUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'chat', content: text, context})
        })
        .then(r => r.text())
        .then(raw => {
            btn.disabled = false;
            btn.textContent = 'Gửi';
            try {
                const d = JSON.parse(raw);
                if (d.success) addMsg(d.reply, true);
                else addMsg('⚠️ ' + (d.message || 'Lỗi không xác định'), true);
            } catch(e) {
                addMsg('⚠️ Lỗi server: ' + raw.substring(0, 150), true);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = 'Gửi';
            addMsg('⚠️ Lỗi kết nối: ' + err.message, true);
        });
    };

    // Ticket analysis function - called from ticket detail page
    window.analyzeTicketWithAI = function(ticketData) {
        const panel = document.getElementById('aiPanel');
        if (panel) panel.style.display = 'flex';

        addMsg('Đang phân tích ticket...', true);

        const aiUrl = (window.APP_BASE || '') + 'api/ai.php';
        fetch(aiUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'analyze_ticket', content: '', context: ticketData})
        })
        .then(r => r.text())
        .then(raw => {
            try {
                const d = JSON.parse(raw);
                if (d.success) addMsg(d.reply, true);
                else addMsg('⚠️ ' + d.message, true);
            } catch(e) {
                addMsg('⚠️ Lỗi server: ' + raw.substring(0, 150), true);
            }
        })
        .catch(err => addMsg('⚠️ Lỗi kết nối: ' + err.message, true));
    };
})();
