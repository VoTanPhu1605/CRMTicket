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
</body>
</html>
