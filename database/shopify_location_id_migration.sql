ALTER TABLE branches 
ADD COLUMN shopify_location_id BIGINT UNSIGNED NULL AFTER branch_code,
ADD UNIQUE KEY uq_branch_shopify_location (shopify_location_id);
