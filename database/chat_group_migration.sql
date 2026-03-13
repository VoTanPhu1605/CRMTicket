-- Chat group & sticker support
ALTER TABLE chat_rooms
    ADD COLUMN IF NOT EXISTS `name` VARCHAR(100) NULL DEFAULT NULL AFTER `type`;

ALTER TABLE chat_messages
    ADD COLUMN IF NOT EXISTS `msg_type` ENUM('text','sticker') NOT NULL DEFAULT 'text' AFTER `message`;
