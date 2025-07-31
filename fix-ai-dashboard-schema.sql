-- Fix AI Model Comparison Dashboard Database Schema
-- Add missing AI analysis columns to quotes table

-- Add the specific AI analysis columns that the dashboard expects
ALTER TABLE quotes 
ADD COLUMN IF NOT EXISTS ai_o4_mini_analysis JSON,
ADD COLUMN IF NOT EXISTS ai_o3_analysis JSON,
ADD COLUMN IF NOT EXISTS ai_gemini_analysis JSON,
ADD COLUMN IF NOT EXISTS ai_o1_mini_analysis JSON;

-- Add an index for better performance when querying AI analysis data
CREATE INDEX IF NOT EXISTS idx_ai_analyses ON quotes(
    ai_o4_mini_analysis((1)), 
    ai_o3_analysis((1)), 
    ai_gemini_analysis((1))
);

-- Update any existing data that might be in the wrong column
-- (This handles the o1_mini vs o4_mini naming confusion)
UPDATE quotes 
SET ai_o4_mini_analysis = ai_o1_mini_analysis 
WHERE ai_o1_mini_analysis IS NOT NULL 
  AND ai_o4_mini_analysis IS NULL;