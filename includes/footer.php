    </div><!-- /.page-content -->
</div><!-- /.main-content -->

<!-- Bootstrap JS (local, with CDN fallback) -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>if (typeof bootstrap === 'undefined') { document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"><\/script>'); }</script>

<script>
// Sidebar toggle (mobile)
const sidebarToggle  = document.getElementById('sidebarToggle');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar()  { sidebar.classList.add('open'); sidebarOverlay.classList.add('open'); }
function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('open'); }

sidebarToggle?.addEventListener('click', openSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);

// Auto-dismiss alerts (skip hidden ones used as error containers in modals)
document.querySelectorAll('.alert:not(.d-none)').forEach(el => {
    if (el.id) return; // skip elements with an ID (used as reusable error containers)
    setTimeout(() => {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 4000);
});
</script>

<script src="assets/js/ai-assistant.js"></script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>

<!-- Global delete confirmation modal -->
<div id="deleteConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px;max-width:400px;width:94%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:42px;height:42px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-trash" style="color:#dc2626;font-size:18px;"></i>
            </div>
            <div>
                <div style="font-weight:700;font-size:15px;">Xác nhận xóa</div>
                <div style="color:#64748b;font-size:13px;">Ticket #<span id="deleteTicketId"></span></div>
            </div>
        </div>
        <p style="color:#475569;font-size:13.5px;margin-bottom:20px;">Hành động này <strong>không thể hoàn tác</strong>. Toàn bộ ghi chú, lịch sử và thanh toán của ticket sẽ bị xóa vĩnh viễn.</p>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="document.getElementById('deleteConfirmModal').style.display='none'" style="padding:8px 20px;border:1px solid #dde1e7;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;">Hủy</button>
            <button id="deleteConfirmBtn" style="padding:8px 24px;background:#dc2626;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">
                <i class="bi bi-trash me-1"></i>Xóa vĩnh viễn
            </button>
        </div>
    </div>
</div>
</body>
</html>
