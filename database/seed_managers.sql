-- ============================================================
-- Seed File: 16 Branch Managers
-- Passwords are set uniquely for each branch (e.g. naeem@321)
-- ============================================================

INSERT INTO users (name, email, password, role, branch_id, status) VALUES
('Al Naeem Manager', 'naeem@sheeralateen.com', '$2b$10$rO41oyTpNj.zsDwEttOf8e8e7.qQuMH9XEqr8pJaMFSYaZbbhcOLa', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'NAEEM'), 'active'),
('Bahrah Manager', 'bahrah@sheeralateen.com', '$2b$10$gKC4H.DtgvFYZI8WzQFp9eUL9hgw4iftxJy1hCotL4IM5P98uwAl6', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'BAHRAH'), 'active'),
('Al Sawari Manager', 'sawari@sheeralateen.com', '$2b$10$4jIqnUbih7OTB1Ez0s5x3.pihbRbp8gEkW3rW/dV45zC5JX00yAmy', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'SAWARI'), 'active'),
('Al Kamil Manager', 'kamil@sheeralateen.com', '$2b$10$4eUuCzCaB5tL98Ov8i7Kzu.mRrgN8oUYl.WCJYpkdWL/10lICqwZi', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'KAMIL'), 'active'),
('Khulais Manager', 'khulais@sheeralateen.com', '$2b$10$cz4YBqocrvTJUp7yGkfQdeyv34EL8fFkzlQENckg5VjI/Wl/KnEVC', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'KHULAIS'), 'active'),
('Gharan Manager', 'gharan@sheeralateen.com', '$2b$10$IJ2c2R3ALWU7vxFfNXWg8ey2xKUirbwoxasUqopcTsKzm596eiVMu', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'GHARAN'), 'active'),
('Al Hamadaniyyah Manager', 'hamadaniyyah@sheeralateen.com', '$2b$10$.9VQUI7E2nNmDYX/kIMPFuTrD/3h/vnGA/SPlWxYGUmkoWqse3TP6', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'HAMADANIYYAH'), 'active'),
('Hira St Manager', 'hira@sheeralateen.com', '$2b$10$iUNrxdvVHDaCx1HLsJz6PO9Pj6LEkXbgh5N.SUI9xGVCzKA48a69y', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'HIRA'), 'active'),
('Al Marwah Manager', 'marwah@sheeralateen.com', '$2b$10$bt3v7RBAvmbc/eDaiemZielRsi5sgbBb43oKNKCbRhbwyER82tt/G', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'MARWAH'), 'active'),
('Al-Safa Manager', 'safa@sheeralateen.com', '$2b$10$sRvgsXdnvJ3H4xWpfYo0luwQR3DNm.6FLw/C9iJd6hnvBSCm7CE6u', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'SAFA'), 'active'),
('An Naseem Manager', 'naseem@sheeralateen.com', '$2b$10$Bqc/0p0aAki1BKcemGbfw.OkefAQfZyIXD0pordQf2g7SgwaF1pMu', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'NASEEM'), 'active'),
('Palestine Manager', 'palestine@sheeralateen.com', '$2b$10$tesGqxY5atB23DNhMrZ6rurDZSER9Mg9Xiby3IDP5HaX/GeowoOn2', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'PALESTINE'), 'active'),
('Quwaizah Manager', 'quwaizah@sheeralateen.com', '$2b$10$rv7kZGfaUZM0zn/0T9RNluJdILbM.nuB/s.nlo.MhEDwfs.Rvh.b.', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'QUWAIZAH'), 'active'),
('Almahameed Manager', 'almahameed@sheeralateen.com', '$2b$10$YnWL758qOck0wjn6K27fvehAGf9HfdJRBMVey1Eko8F5DQVB1fVDq', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'MAHAMEED'), 'active'),
('Harazath Manager', 'harazath@sheeralateen.com', '$2b$10$yq0LX/dMRNguNuv0XkNteuTtp8poKB3O5oEnUrKgKdx7uYx5vZagW', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'HARAZATH'), 'active'),
('Taneem Manager', 'taneem@sheeralateen.com', '$2b$10$xo9SDvjFYLNlCt/YHnUXDuH.HpKka4MDoo/rIL/9CTNEvDI3qk9Sq', 'branch_manager', (SELECT id FROM branches WHERE branch_code = 'TANEEM'), 'active');

-- Link the managers back to their branches
UPDATE branches b 
JOIN users u ON u.branch_id = b.id 
SET b.branch_manager_id = u.id 
WHERE u.role = 'branch_manager' AND b.branch_manager_id IS NULL;
