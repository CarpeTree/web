-- Update quote_status ENUM to include new multi-AI statuses
-- Run this once on your live server to add the new status values

ALTER TABLE quotes 
MODIFY COLUMN quote_status ENUM(
    'submitted', 
    'ai_processing', 
    'multi_ai_processing',
    'draft_ready', 
    'multi_ai_complete',
    'sent_to_client', 
    'accepted', 
    'rejected', 
    'expired'
) DEFAULT 'submitted';