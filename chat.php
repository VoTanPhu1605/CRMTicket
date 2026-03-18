<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'models/Chat.php';
require_once 'models/User.php';

requireLogin();
$me = getCurrentUser();
$chat = new Chat();
$userModel = new User();

// ── Page render ────────────────────────────────────────────────────────────────
$contacts   = $chat->getContacts($me['id']);
$groupRooms = $chat->getGroupRooms((int)$me['id']);
$activeRoomId = (int)($_GET['room'] ?? 1);
if ($activeRoomId < 1) $activeRoomId = 1;

global $pdo;
$pdo->prepare("INSERT IGNORE INTO chat_members (room_id, user_id) VALUES (1,?)")->execute([$me['id']]);
$chat->markRead($activeRoomId, $me['id']);
$initMessages = $chat->getMessages($activeRoomId, 0);
$lastId = !empty($initMessages) ? (int)end($initMessages)['id'] : 0;

// Active room name/type
$activeRoomName = 'Nhóm IT chung';
$activeRoomType = 'general';
$activeRoomSub  = 'Tất cả nhân viên';

if ($activeRoomId !== 1) {
    $roomInfo = $chat->getRoomInfo($activeRoomId);
    if ($roomInfo) {
        if ($roomInfo['type'] === 'group') {
            $activeRoomName = $roomInfo['name'];
            $activeRoomType = 'group';
            $mc = $chat->getMemberCount($activeRoomId);
            $activeRoomSub  = $mc . ' thành viên';
        } elseif ($roomInfo['type'] === 'direct') {
            $partner = $chat->getDirectPartner($activeRoomId, $me['id']);
            if ($partner) {
                $activeRoomName = $partner['fullname'];
                $activeRoomType = 'direct';
                $activeRoomSub  = 'Tin nhắn trực tiếp';
            }
        }
    }
}

// DM rooms
$stmt = $pdo->prepare("
    SELECT cr.id, cr.type, cm2.user_id AS partner_id,
           u.fullname AS partner_name, r.name AS partner_role
    FROM chat_rooms cr
    JOIN chat_members cm  ON cm.room_id  = cr.id AND cm.user_id = ?
    JOIN chat_members cm2 ON cm2.room_id = cr.id AND cm2.user_id != ?
    JOIN users u ON u.id = cm2.user_id
    JOIN roles r ON r.id = u.role_id
    WHERE cr.type = 'direct'
    ORDER BY cr.id DESC
");
$stmt->execute([$me['id'], $me['id']]);
$dmRooms = $stmt->fetchAll();

$pageTitle = 'Chat nội bộ';
include 'includes/header.php';

function roleColor($role) {
    return match($role) {
        'Admin'       => '#4f46e5',
        'Manager'     => '#0891b2',
        'IT Helpdesk' => '#7c3aed',
        'IT Support'  => '#059669',
        'IT Intern'   => '#d97706',
        default       => '#6b7280',
    };
}
?>

<style>
.chat-wrap{display:flex;gap:0;height:calc(100vh - 130px);min-height:460px;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow)}
.chat-sidebar{width:265px;flex-shrink:0;background:#fff;border-right:1px solid var(--border);display:flex;flex-direction:column}
.chat-sidebar-header{padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;font-size:14px;color:var(--text);display:flex;align-items:center;justify-content:space-between}
.chat-room-list{flex:1;overflow-y:auto;padding:6px 0}
.chat-section-label{font-size:10.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;padding:10px 16px 4px}
.chat-room-item{display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;transition:.15s;border-left:3px solid transparent}
.chat-room-item:hover{background:var(--bg)}
.chat-room-item.active{background:#eef2fb;border-left-color:var(--primary)}
.chat-room-item .room-name{font-size:13.5px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-room-item .room-preview{font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-room-avatar{width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;flex-shrink:0}
.chat-sidebar-footer{padding:10px 14px;border-top:1px solid var(--border);display:flex;gap:6px}
.chat-main{flex:1;display:flex;flex-direction:column;background:#f5f7fa;min-width:0}
.chat-main-header{padding:12px 18px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.chat-messages{flex:1;overflow-y:auto;padding:16px 18px;display:flex;flex-direction:column;gap:4px}
.chat-msg{display:flex;gap:8px;max-width:80%;align-items:flex-end}
.chat-msg.mine{align-self:flex-end;flex-direction:row-reverse}
.chat-msg-bubble{padding:8px 12px;border-radius:14px;font-size:13.5px;line-height:1.5;overflow-wrap:break-word;word-break:normal;max-width:100%;min-width:48px}
.chat-msg .chat-msg-bubble{background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.07);border-bottom-left-radius:4px}
.chat-msg.mine .chat-msg-bubble{background:var(--primary);color:#fff;border-bottom-right-radius:4px}
.chat-msg-sticker{font-size:52px;line-height:1;background:none!important;box-shadow:none!important;padding:4px 6px!important;min-width:unset}
.chat-msg-avatar{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;align-self:flex-end}
.chat-msg-meta{font-size:10.5px;color:var(--text-muted);margin-bottom:2px}
.chat-msg.mine .chat-msg-meta{text-align:right}
.chat-msg-group{display:flex;flex-direction:column;max-width:76%}
.chat-input-area{padding:10px 14px;background:#fff;border-top:1px solid var(--border);display:flex;gap:8px;align-items:flex-end;position:relative}
.chat-input{flex:1;border:1px solid var(--border);border-radius:20px;padding:8px 14px;font-size:13.5px;resize:none;outline:none;max-height:100px;min-height:38px;line-height:1.4;font-family:inherit}
.chat-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.chat-send-btn{width:38px;height:38px;border-radius:50%;background:var(--primary);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:.15s}
.chat-send-btn:hover{background:var(--primary-dark)}
.chat-icon-btn{width:34px;height:34px;border-radius:50%;background:none;border:1px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;transition:.15s;color:var(--text-muted)}
.chat-icon-btn:hover{background:var(--bg);color:var(--primary)}
.unread-dot{width:8px;height:8px;background:#ef4444;border-radius:50%;flex-shrink:0}
.badge-unread{background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 5px;border-radius:100px;line-height:1.5}
.chat-date-sep{text-align:center;font-size:11px;color:var(--text-muted);margin:8px 0;position:relative}
.chat-date-sep::before,.chat-date-sep::after{content:'';position:absolute;top:50%;width:38%;height:1px;background:var(--border)}
.chat-date-sep::before{left:0}.chat-date-sep::after{right:0}
/* Sticker picker */
.sticker-picker{position:absolute;bottom:58px;left:14px;background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.12);padding:10px;display:none;z-index:100;width:300px}
.sticker-picker.show{display:block}
.sticker-picker-tabs{display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap}
.sticker-tab{font-size:11px;padding:2px 8px;border-radius:100px;border:1px solid var(--border);cursor:pointer;background:none;transition:.15s}
.sticker-tab.active,.sticker-tab:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.sticker-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:4px}
.sticker-item{font-size:28px;text-align:center;cursor:pointer;border-radius:8px;padding:4px;transition:.15s;line-height:1}
.sticker-item:hover{background:var(--bg);transform:scale(1.2)}
@media(max-width:640px){
  .chat-sidebar{width:200px}
  .chat-msg,.chat-msg.mine{max-width:90%}
  .chat-wrap{height:calc(100vh - 110px)}
  .chat-section-label{font-size:9.5px;padding:8px 10px 3px}
  .chat-room-item{padding:8px 10px}
  .chat-room-avatar{width:30px;height:30px;font-size:11px}
  .chat-sidebar-footer{padding:8px 10px;gap:4px}
  .chat-sidebar-footer .btn{font-size:11px;padding:4px 8px}
}
@media(max-width:480px){
  /* On very small screens, hide sidebar behind a toggle */
  .chat-wrap{position:relative;overflow:visible}
  .chat-sidebar{position:absolute;left:0;top:0;bottom:0;z-index:50;transform:translateX(-100%);transition:.2s;height:100%}
  .chat-sidebar.show{transform:translateX(0);box-shadow:var(--shadow-lg)}
  .chat-main{width:100%}
  /* Show a hamburger to open the sidebar */
  .chat-sidebar-toggle{display:flex!important}
}
.chat-sidebar-toggle{display:none;width:32px;height:32px;border-radius:8px;background:var(--bg);border:1px solid var(--border);align-items:center;justify-content:center;cursor:pointer;font-size:15px;color:var(--text-muted);flex-shrink:0}
</style>

<div class="chat-wrap">
    <!-- Sidebar -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <span><i class="bi bi-chat-dots-fill me-2" style="color:var(--primary)"></i>Chat nội bộ</span>
        </div>
        <div class="chat-room-list" id="roomList">
            <!-- General -->
            <div class="chat-section-label">Nhóm</div>
            <div class="chat-room-item <?php echo $activeRoomId===1 ? 'active' : ''; ?>"
                 data-room="1" onclick="switchRoom(1,'Nhóm IT chung','general','Tất cả nhân viên')">
                <div class="chat-room-avatar" style="background:#4f46e5;border-radius:10px;width:36px;height:36px">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="room-name">Nhóm IT chung</div>
                    <div class="room-preview">Tất cả nhân viên</div>
                </div>
                <span class="badge-unread" id="unread-1" style="display:none"></span>
            </div>
            <!-- Custom groups -->
            <?php foreach ($groupRooms as $gr): ?>
            <div class="chat-room-item <?php echo $activeRoomId===$gr['id'] ? 'active' : ''; ?>"
                 data-room="<?php echo $gr['id']; ?>"
                 onclick="switchRoom(<?php echo $gr['id']; ?>,<?php echo htmlspecialchars(json_encode($gr['name']),ENT_QUOTES); ?>,'group',<?php echo (int)$gr['member_count']; ?>+' thành viên')">
                <div class="chat-room-avatar" style="background:#7c3aed;border-radius:10px;width:36px;height:36px">
                    <i class="bi bi-people"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="room-name"><?php echo htmlspecialchars($gr['name']); ?></div>
                    <div class="room-preview"><?php echo $gr['member_count']; ?> thành viên</div>
                </div>
                <span class="badge-unread" id="unread-<?php echo $gr['id']; ?>" style="display:none"></span>
            </div>
            <?php endforeach; ?>

            <!-- DMs -->
            <?php if (!empty($dmRooms)): ?>
            <div class="chat-section-label" style="margin-top:4px">Tin nhắn trực tiếp</div>
            <?php foreach ($dmRooms as $dm): ?>
            <div class="chat-room-item <?php echo $activeRoomId===$dm['id'] ? 'active' : ''; ?>"
                 data-room="<?php echo $dm['id']; ?>"
                 onclick="switchRoom(<?php echo $dm['id']; ?>,<?php echo htmlspecialchars(json_encode($dm['partner_name']),ENT_QUOTES); ?>,'direct','Tin nhắn trực tiếp')">
                <div class="chat-room-avatar" style="background:<?php echo roleColor($dm['partner_role']); ?>;width:36px;height:36px">
                    <?php echo mb_strtoupper(mb_substr($dm['partner_name'],0,1)); ?>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="room-name"><?php echo htmlspecialchars($dm['partner_name']); ?></div>
                    <div class="room-preview"><?php echo htmlspecialchars($dm['partner_role']); ?></div>
                </div>
                <span class="badge-unread" id="unread-<?php echo $dm['id']; ?>" style="display:none"></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="chat-sidebar-footer">
            <button class="btn btn-outline-primary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#newChatModal">
                <i class="bi bi-chat-left-text me-1"></i>Chat mới
            </button>
            <button class="btn btn-outline-secondary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#newGroupModal">
                <i class="bi bi-people me-1"></i>Tạo nhóm
            </button>
        </div>
    </div>

    <!-- Main chat -->
    <div class="chat-main">
        <div class="chat-main-header">
            <button class="chat-sidebar-toggle" onclick="document.querySelector('.chat-sidebar').classList.toggle('show')" title="Danh sách chat">
                <i class="bi bi-layout-sidebar"></i>
            </button>
            <div class="chat-room-avatar" id="headerAvatar"
                 style="background:<?php echo $activeRoomType==='direct' ? 'var(--primary)' : ($activeRoomType==='group' ? '#7c3aed' : '#4f46e5'); ?>;border-radius:<?php echo $activeRoomType==='direct' ? '50%' : '10px'; ?>">
                <?php if ($activeRoomType==='direct'): ?>
                    <?php echo mb_strtoupper(mb_substr($activeRoomName,0,1)); ?>
                <?php else: ?>
                    <i class="bi bi-people<?php echo $activeRoomType==='group' ? '' : '-fill'; ?>"></i>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-weight:600;font-size:14px" id="headerName"><?php echo htmlspecialchars($activeRoomName); ?></div>
                <div style="font-size:11.5px;color:var(--text-muted)" id="headerSub"><?php echo $activeRoomSub; ?></div>
            </div>
        </div>

        <div class="chat-messages" id="msgContainer">
            <?php echo renderMessages($initMessages, $me['id']); ?>
        </div>

        <div class="chat-input-area">
            <!-- Sticker picker -->
            <div class="sticker-picker" id="stickerPicker">
                <div class="sticker-grid" id="stickerGrid"></div>
            </div>
            <button class="chat-icon-btn" onclick="toggleStickers()" title="Sticker">😊</button>
            <textarea class="chat-input" id="msgInput" placeholder="Nhập tin nhắn..." rows="1"
                      onkeydown="handleKey(event)"></textarea>
            <button class="chat-send-btn" onclick="sendMsg()">
                <i class="bi bi-send-fill"></i>
            </button>
        </div>
    </div>
</div>

<!-- New DM Modal -->
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Chat trực tiếp</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <input type="text" class="form-control form-control-sm mb-2" id="contactSearch"
                       placeholder="Tìm nhân viên..." oninput="filterContacts(this.value)">
                <div id="contactList" style="max-height:280px;overflow-y:auto">
                    <?php foreach ($contacts as $c): ?>
                    <div class="chat-room-item contact-item" data-name="<?php echo strtolower(htmlspecialchars($c['fullname'])); ?>"
                         onclick="openDM(<?php echo $c['id']; ?>, <?php echo htmlspecialchars(json_encode($c['fullname']),ENT_QUOTES); ?>)"
                         style="padding:8px 10px;border-radius:8px;cursor:pointer">
                        <div class="chat-room-avatar" style="background:<?php echo roleColor($c['role_name']); ?>;width:32px;height:32px;font-size:12px">
                            <?php echo mb_strtoupper(mb_substr($c['fullname'],0,1)); ?>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:500"><?php echo htmlspecialchars($c['fullname']); ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?php echo htmlspecialchars($c['role_name']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Group Modal -->
<div class="modal fade" id="newGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-people me-2"></i>Tạo nhóm chat</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="groupError" class="alert alert-danger d-none py-2"></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tên nhóm <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="groupName" placeholder="VD: Team Frontend, Dự án ABC...">
                </div>
                <div class="mb-1">
                    <label class="form-label fw-semibold">Thêm thành viên <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm mb-2" id="groupSearch"
                           placeholder="Tìm nhân viên..." oninput="filterGroupMembers(this.value)">
                </div>
                <div style="max-height:220px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:4px" id="groupMemberList">
                    <?php foreach ($contacts as $c): ?>
                    <label class="d-flex align-items-center gap-2 p-2 rounded member-row" style="cursor:pointer"
                           data-name="<?php echo strtolower(htmlspecialchars($c['fullname'])); ?>">
                        <input type="checkbox" class="form-check-input m-0 group-member-cb" value="<?php echo $c['id']; ?>">
                        <div class="chat-room-avatar" style="background:<?php echo roleColor($c['role_name']); ?>;width:28px;height:28px;font-size:11px;flex-shrink:0">
                            <?php echo mb_strtoupper(mb_substr($c['fullname'],0,1)); ?>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:500"><?php echo htmlspecialchars($c['fullname']); ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?php echo htmlspecialchars($c['role_name']); ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="createGroup()">
                    <i class="bi bi-plus-circle me-1"></i>Tạo nhóm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRoom = <?php echo $activeRoomId; ?>;
let lastMsgId   = <?php echo $lastId; ?>;
let pollTimer   = null;
const myId      = <?php echo (int)$me['id']; ?>;

// ── Stickers ──────────────────────────────────────────────────────────────────
const STICKERS = [
    '😀','😂','🤣','😍','🥰','😎','😭','😱','😤','😡','🤔','😴',
    '🥳','🤩','😇','😏','🙃','🤗','🫡','😬','🥹','😆','🤪','😜',
    '👍','👎','❤️','🔥','💯','🎉','👏','🙏','💪','🤦','🤷','🫶',
    '🎊','⭐','🏆','🎮','🍕','🍔','🧋','🎂','🌈','💎','🚀','🐶',
];

(function initStickers() {
    const grid = document.getElementById('stickerGrid');
    STICKERS.forEach(s => {
        const el = document.createElement('div');
        el.className = 'sticker-item';
        el.textContent = s;
        el.onclick = () => sendSticker(s);
        grid.appendChild(el);
    });
})();

function toggleStickers() {
    document.getElementById('stickerPicker').classList.toggle('show');
}

document.addEventListener('click', e => {
    const picker = document.getElementById('stickerPicker');
    if (!picker.contains(e.target) && !e.target.closest('.chat-icon-btn')) {
        picker.classList.remove('show');
    }
});

function sendSticker(emoji) {
    document.getElementById('stickerPicker').classList.remove('show');
    const inp = document.getElementById('msgInput');
    inp.value += emoji;
    inp.focus();
    inp.style.height = 'auto';
    inp.style.height = Math.min(inp.scrollHeight, 100) + 'px';
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtTime(dt) {
    const d = new Date(dt.replace(' ','T'));
    return d.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'});
}
function fmtDate(dt) {
    const d = new Date(dt.replace(' ','T'));
    const today = new Date();
    const diff  = Math.floor((today - d) / 86400000);
    if (diff === 0) return 'Hôm nay';
    if (diff === 1) return 'Hôm qua';
    return d.toLocaleDateString('vi-VN');
}
function avatarColor(name) {
    const colors = ['#4f46e5','#0891b2','#7c3aed','#059669','#d97706','#dc2626'];
    let h = 0; for (const c of name) h = (h * 31 + c.charCodeAt(0)) & 0xffff;
    return colors[h % colors.length];
}
function buildBubble(msg, prevSenderId, prevDate) {
    const mine    = msg.sender_id == myId;
    const dt      = msg.created_at;
    const isStick = msg.msg_type === 'sticker';
    let html = '';
    const msgDate = fmtDate(dt);
    if (msgDate !== prevDate) {
        html += `<div class="chat-date-sep">${escHtml(msgDate)}</div>`;
    }
    const showMeta = msg.sender_id != prevSenderId || msgDate !== prevDate;
    const av = mine ? '' : `<div class="chat-msg-avatar" style="background:${avatarColor(msg.sender_name)}">${escHtml(msg.sender_name.charAt(0).toUpperCase())}</div>`;
    const meta = showMeta && !mine ? `<div class="chat-msg-meta">${escHtml(msg.sender_name)} · ${fmtTime(dt)}</div>`
               : showMeta && mine  ? `<div class="chat-msg-meta">${fmtTime(dt)}</div>` : '';
    const bubbleClass = 'chat-msg-bubble' + (isStick ? ' chat-msg-sticker' : '');
    const content = isStick ? escHtml(msg.message) : escHtml(msg.message).replace(/\n/g,'<br>');
    html += `<div class="chat-msg ${mine ? 'mine' : ''}">
        ${mine ? '' : av}
        <div class="chat-msg-group">
            ${meta}
            <div class="${bubbleClass}">${content}</div>
        </div>
        ${mine ? av : ''}
    </div>`;
    return { html, date: msgDate, senderId: msg.sender_id };
}
function renderAll(msgs) {
    let html = '', prevSender = null, prevDate = null;
    for (const m of msgs) {
        const r = buildBubble(m, prevSender, prevDate);
        html += r.html;
        prevSender = r.senderId;
        prevDate   = r.date;
    }
    return html;
}

// ── Send ──────────────────────────────────────────────────────────────────────
function sendMsg() {
    const inp = document.getElementById('msgInput');
    const txt = inp.value.trim();
    if (!txt) return;
    inp.value = '';
    inp.style.height = '';
    fetch('chat-api.php?ajax=send', {
        method: 'POST',
        body: new URLSearchParams({room_id: currentRoom, message: txt, type: 'text'})
    }).then(() => poll());
}
function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
    const el = e.target;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

// ── Poll ──────────────────────────────────────────────────────────────────────
function poll() {
    fetch(`chat-api.php?ajax=poll&room_id=${currentRoom}&after=${lastMsgId}`)
        .then(r => r.text())
        .then(text => {
            let msgs; try { msgs = JSON.parse(text); } catch(e) { return; }
            if (msgs.length) {
                const container = document.getElementById('msgContainer');
                let prevSender = null, prevDate = null;
                msgs.forEach(m => {
                    const r = buildBubble(m, prevSender, prevDate);
                    container.insertAdjacentHTML('beforeend', r.html);
                    prevSender = r.senderId; prevDate = r.date;
                });
                lastMsgId = msgs[msgs.length-1].id;
                container.scrollTop = container.scrollHeight;
            }
        });
}
function startPoll() {
    clearInterval(pollTimer);
    pollTimer = setInterval(poll, 3000);
}

// ── Unread badges ─────────────────────────────────────────────────────────────
function refreshUnread() {
    fetch('chat-api.php?ajax=unread_counts').then(r => r.json()).then(counts => {
        for (const [rid, cnt] of Object.entries(counts)) {
            if (parseInt(rid) === currentRoom) continue;
            const el = document.getElementById('unread-' + rid);
            if (el) { el.textContent = cnt; el.style.display = cnt > 0 ? '' : 'none'; }
        }
    });
}

// ── Switch room ───────────────────────────────────────────────────────────────
function switchRoom(roomId, name, type, sub) {
    if (roomId === currentRoom) return;
    currentRoom = roomId; lastMsgId = 0;
    document.querySelectorAll('.chat-room-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.room) === roomId);
    });
    document.getElementById('headerName').textContent = name;
    document.getElementById('headerSub').textContent  = sub || (type === 'direct' ? 'Tin nhắn trực tiếp' : 'Nhóm chat');
    const av = document.getElementById('headerAvatar');
    if (type === 'direct') {
        av.innerHTML = name.charAt(0).toUpperCase();
        av.style.background = avatarColor(name);
        av.style.borderRadius = '50%';
    } else {
        av.innerHTML = '<i class="bi bi-people' + (type === 'general' ? '-fill' : '') + '"></i>';
        av.style.background = type === 'general' ? '#4f46e5' : '#7c3aed';
        av.style.borderRadius = '10px';
    }
    fetch(`chat-api.php?ajax=poll&room_id=${roomId}&after=0`)
        .then(r => r.text())
        .then(text => {
            let msgs; try { msgs = JSON.parse(text); } catch(e) { msgs = []; }
            const c = document.getElementById('msgContainer');
            c.innerHTML = renderAll(msgs);
            if (msgs.length) lastMsgId = msgs[msgs.length-1].id;
            c.scrollTop = c.scrollHeight;
        });
    const badge = document.getElementById('unread-' + roomId);
    if (badge) badge.style.display = 'none';
    history.replaceState(null, '', 'chat.php?room=' + roomId);
    // Close sidebar on mobile after switching
    if (window.innerWidth <= 480) document.querySelector('.chat-sidebar')?.classList.remove('show');
    startPoll();
}

// ── Open DM ───────────────────────────────────────────────────────────────────
function openDM(userId, userName) {
    fetch('chat-api.php?ajax=open_direct', {method:'POST', body: new URLSearchParams({user_id: userId})})
    .then(r => r.text()).then(text => {
        let d; try { d = JSON.parse(text); } catch(e) { alert('Lỗi: ' + text.substring(0,200)); return; }
        if (!d.ok) { alert(d.message || 'Không thể mở chat.'); return; }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newChatModal')).hide();
        const roomId = d.room_id;
        if (!document.querySelector(`[data-room="${roomId}"]`)) {
            // Add DM section if needed
            let dmSection = document.getElementById('dmSection');
            if (!dmSection) {
                dmSection = document.createElement('div');
                dmSection.id = 'dmSection';
                dmSection.innerHTML = '<div class="chat-section-label" style="margin-top:4px">Tin nhắn trực tiếp</div>';
                document.getElementById('roomList').appendChild(dmSection);
            }
            const item = document.createElement('div');
            item.className = 'chat-room-item';
            item.dataset.room = roomId;
            item.onclick = () => switchRoom(roomId, userName, 'direct', 'Tin nhắn trực tiếp');
            item.innerHTML = `
                <div class="chat-room-avatar" style="background:${avatarColor(userName)};width:36px;height:36px">${userName.charAt(0).toUpperCase()}</div>
                <div style="flex:1;min-width:0">
                    <div class="room-name">${escHtml(userName)}</div>
                    <div class="room-preview" style="font-size:11px;color:var(--text-muted)">Tin nhắn trực tiếp</div>
                </div>
                <span class="badge-unread" id="unread-${roomId}" style="display:none"></span>`;
            document.getElementById('roomList').appendChild(item);
        }
        switchRoom(roomId, userName, 'direct', 'Tin nhắn trực tiếp');
    });
}

// ── Create group ──────────────────────────────────────────────────────────────
function createGroup() {
    const name    = document.getElementById('groupName').value.trim();
    const members = [...document.querySelectorAll('.group-member-cb:checked')].map(el => el.value);
    const errEl   = document.getElementById('groupError');
    if (!name) { errEl.textContent = 'Vui lòng đặt tên nhóm.'; errEl.classList.remove('d-none'); return; }
    if (members.length < 1) { errEl.textContent = 'Chọn ít nhất 1 thành viên.'; errEl.classList.remove('d-none'); return; }
    errEl.classList.add('d-none');

    const body = new URLSearchParams({name});
    members.forEach(m => body.append('members[]', m));

    fetch('chat-api.php?ajax=create_group', {method:'POST', body})
    .then(r => r.json()).then(d => {
        if (!d.ok) { errEl.textContent = d.message; errEl.classList.remove('d-none'); return; }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newGroupModal')).hide();
        document.getElementById('groupName').value = '';
        document.querySelectorAll('.group-member-cb').forEach(cb => cb.checked = false);

        const roomId = d.room_id;
        if (!document.querySelector(`[data-room="${roomId}"]`)) {
            const item = document.createElement('div');
            item.className = 'chat-room-item';
            item.dataset.room = roomId;
            item.onclick = () => switchRoom(roomId, d.name, 'group', d.member_count + ' thành viên');
            item.innerHTML = `
                <div class="chat-room-avatar" style="background:#7c3aed;border-radius:10px;width:36px;height:36px"><i class="bi bi-people"></i></div>
                <div style="flex:1;min-width:0">
                    <div class="room-name">${escHtml(d.name)}</div>
                    <div class="room-preview">${d.member_count} thành viên</div>
                </div>
                <span class="badge-unread" id="unread-${roomId}" style="display:none"></span>`;
            // Insert after general room
            const general = document.querySelector('[data-room="1"]');
            general.insertAdjacentElement('afterend', item);
        }
        switchRoom(roomId, d.name, 'group', d.member_count + ' thành viên');
    });
}

// ── Filters ───────────────────────────────────────────────────────────────────
function filterContacts(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.contact-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}
function filterGroupMembers(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.member-row').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const c = document.getElementById('msgContainer');
    c.scrollTop = c.scrollHeight;
    startPoll();
    setInterval(refreshUnread, 5000);
    refreshUnread();
});
</script>

<?php
function renderMessages(array $msgs, int $myId): string {
    $html = '';
    $prevSender = null;
    $prevDate   = null;
    foreach ($msgs as $m) {
        $mine    = (int)$m['sender_id'] === $myId;
        $isStick = ($m['msg_type'] ?? 'text') === 'sticker';
        $dt   = $m['created_at'];
        $d    = new DateTime($dt);
        $today = new DateTime('today');
        $diff  = (int)$today->diff($d)->format('%a');
        $dateLabel = match(true) {
            $diff === 0 => 'Hôm nay',
            $diff === 1 => 'Hôm qua',
            default     => $d->format('d/m/Y'),
        };
        if ($dateLabel !== $prevDate)
            $html .= '<div class="chat-date-sep">' . htmlspecialchars($dateLabel) . '</div>';
        $showMeta = $m['sender_id'] !== $prevSender || $dateLabel !== $prevDate;
        $initial  = mb_strtoupper(mb_substr($m['sender_name'], 0, 1));
        $colors   = ['#4f46e5','#0891b2','#7c3aed','#059669','#d97706','#dc2626'];
        $hash = 0; foreach (str_split($m['sender_name']) as $ch) $hash = ($hash * 31 + ord($ch)) & 0xffff;
        $color    = $colors[$hash % count($colors)];
        $time     = $d->format('H:i');
        $bubbleCls = 'chat-msg-bubble' . ($isStick ? ' chat-msg-sticker' : '');
        $msgHtml  = $isStick ? htmlspecialchars($m['message']) : nl2br(htmlspecialchars($m['message']));
        $av       = "<div class='chat-msg-avatar' style='background:{$color}'>{$initial}</div>";
        $meta     = $showMeta ? "<div class='chat-msg-meta'>" . ($mine ? $time : htmlspecialchars($m['sender_name']) . " · {$time}") . "</div>" : '';
        $html .= "<div class='chat-msg " . ($mine ? 'mine' : '') . "'>
            " . ($mine ? '' : $av) . "
            <div class='chat-msg-group'>{$meta}<div class='{$bubbleCls}'>{$msgHtml}</div></div>
            " . ($mine ? $av : '') . "
        </div>";
        $prevSender = $m['sender_id'];
        $prevDate   = $dateLabel;
    }
    return $html;
}
?>

<?php include 'includes/footer.php'; ?>
