I have successfully implemented a separate Messenger feature for Mardin High School with the following components:

**Database Changes:**
- Created `chat_attachments` table to store message attachments (files, images, voice, video)
- Table structure mirrors `support_attachments` but for the chat system
- Uses foreign key to `chat_messages` table with CASCADE delete

**API Endpoint:**
- Created `/api/chat-attachment.php` for secure attachment delivery
- Verifies user is a participant in the conversation before serving files
- Supports inline viewing for images, video, audio, PDF
- Forces download for other file types
- Stores files in `/assets/uploads/chat/` directory

**Chat System Modifications (`chat.php`):**
1. **Message Sending:**
   - Added file input with multiple selection and accepted file types (images, audio, video, PDF, DOC, DOCX)
   - Added envelope icon (paperclip) to trigger file selection
   - Processes attachments on message send: validates file types, stores files securely, saves records to `chat_attachments`
   - Uses transactions to ensure atomicity (message + attachments)
   - Sends notifications to all conversation participants (except sender) when new message is sent
   - Redirects after send to prevent resubmission

2. **Message Display:**
   - Added attachment section below each message
   - Shows thumbnails for images, embedded players for video/audio, PDF icons, file icons for documents
   - All attachments are downloadable/viewable via secure API endpoint

3. **UI/UX:**
   - Maintained existing conversation list and chat interface
   - Added attachment count indicator on paperclip icon when files selected
   - Preserved all existing functionality (read status, timestamps, notifications, role-based restrictions)

**Security & Separation:**
- Messenger uses completely separate tables (`chat_messages`, `chat_attachments`, `conversations`, `conversation_members`)
- No overlap with support system tables (`support_tickets`, `support_messages`, `support_attachments`, etc.)
- Attachment storage directory is separate (`/assets/uploads/chat/` vs `/assets/uploads/support/`)
- All file access verified through conversation participation checks
- Role-based contact restrictions preserved (admin global access, others require approval or class-limited)
- Notifications use distinct title "Messenger: New message" to distinguish from support notifications

**File Type Support:**
- Images: JPEG, PNG, GIF, WebP
- Audio: MP3, WAV, WebM
- Video: MP4, QuickTime, WebM
- Documents: PDF, DOC, DOCX

The Messenger is now fully functional as a separate feature within the existing chat tab, meeting all requirements for authorized one-to-one and group communication with secure attachments, notifications, and proper permission boundaries.

The initial error "Call to undefined function e()" has been fixed by ensuring `auth.php` (which includes `functions.php` where `e()` is defined) is included at the top of `chat.php`.