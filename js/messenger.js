// js/messenger.js
const MAX_FILE = 10 * 1024 * 1024; // 10MB

function validateFile(file) {
    if (!file) return { ok: true };
    if (file.size > MAX_FILE) return { ok: false, msg: "Max file size is 10 MB" };

    const allowedExt = ["jpg", "jpeg", "png", "webp", "gif", "pdf", "txt", "html", "htm", "css", "js", "json", "xml", "md", "mp3", "wav", "m4a", "ogg", "webm"];
    const name = file.name || "";
    const ext = name.includes(".") ? name.split(".").pop().toLowerCase() : "";
    if (ext && !allowedExt.includes(ext)) return { ok: false, msg: "This file type is not allowed" };
    return { ok: true };
}

function sendDMMessage() {
    const receiverId = document.getElementById("receiver_id").value;
    const msg = document.getElementById("dm_message").value.trim();
    const fileInput = document.getElementById("dm_file");
    const file = fileInput.files[0] || null;

    const v = validateFile(file);
    if (!v.ok) { alert(v.msg); return; }
    if (!msg && !file) { return; }

    const fd = new FormData();
    fd.append("receiver_id", receiverId);
    fd.append("message", msg);
    if (file) fd.append("attachment", file);

    // Show sending indicator
    const sendBtn = document.querySelector('.input-send');
    const originalHtml = sendBtn.innerHTML;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    sendBtn.disabled = true;

    fetch('send_message.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            document.getElementById("dm_message").value = "";
            fileInput.value = "";
            // Add message to UI without reload
            appendMessage(data);
        } else {
            alert(data.error || 'Failed to send');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to send message');
    })
    .finally(() => {
        sendBtn.innerHTML = originalHtml;
        sendBtn.disabled = false;
    });
}

function appendMessage(messageData) {
    const messagesArea = document.getElementById('messagesArea');
    const isMe = messageData.sender_id === parseInt(document.querySelector('input[name="user_id"]')?.value || '0');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `bubble-row ${isMe ? 'bubble-row-me' : 'bubble-row-them'}`;
    messageDiv.dataset.id = messageData.id;
    
    let attachmentHtml = '';
    if (messageData.attachment) {
        const fileName = messageData.attachment.split('/').pop();
        const isImage = /\.(jpg|jpeg|png|webp|gif)$/i.test(fileName);
        
        if (isImage) {
            attachmentHtml = `<div class="attachment" style="margin-top: 8px;">
                <img src="${messageData.attachment}" alt="Attachment" 
                     style="max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer;"
                     onclick="window.open(this.src, '_blank')">
            </div>`;
        } else {
            attachmentHtml = `<div class="attachment" style="margin-top: 8px;">
                <a href="${messageData.attachment}" target="_blank" 
                   style="display: inline-flex; align-items: center; gap: 8px; 
                          padding: 8px 12px; background: #f0f2f5; border-radius: 8px;
                          text-decoration: none; color: #111b21;">
                    <i class="fas fa-file"></i>
                    <span>${fileName}</span>
                    <i class="fas fa-download" style="margin-left: 8px;"></i>
                </a>
            </div>`;
        }
    }
    
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    messageDiv.innerHTML = `
        <div class="bubble">
            ${messageData.message ? escapeHtml(messageData.message).replace(/\n/g, '<br>') : ''}
            ${attachmentHtml}
        </div>
        <div class="bubble-time">${time}</div>
    `;
    
    messagesArea.appendChild(messageDiv);
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}