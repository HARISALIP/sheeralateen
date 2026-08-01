const bcrypt = require('bcryptjs');
const fs = require('fs');

const branches = [
    { name: 'Al Naeem', email: 'naeem@sheeralateen.com', code: 'NAEEM', prefix: 'naeem' },
    { name: 'Bahrah', email: 'bahrah@sheeralateen.com', code: 'BAHRAH', prefix: 'bahrah' },
    { name: 'Al Sawari', email: 'sawari@sheeralateen.com', code: 'SAWARI', prefix: 'sawari' },
    { name: 'Al Kamil', email: 'kamil@sheeralateen.com', code: 'KAMIL', prefix: 'kamil' },
    { name: 'Khulais', email: 'khulais@sheeralateen.com', code: 'KHULAIS', prefix: 'khulais' },
    { name: 'Gharan', email: 'gharan@sheeralateen.com', code: 'GHARAN', prefix: 'gharan' },
    { name: 'Al Hamadaniyyah', email: 'hamadaniyyah@sheeralateen.com', code: 'HAMADANIYYAH', prefix: 'hamadaniyyah' },
    { name: 'Hira St', email: 'hira@sheeralateen.com', code: 'HIRA', prefix: 'hira' },
    { name: 'Al Marwah', email: 'marwah@sheeralateen.com', code: 'MARWAH', prefix: 'marwah' },
    { name: 'Al-Safa', email: 'safa@sheeralateen.com', code: 'SAFA', prefix: 'safa' },
    { name: 'An Naseem', email: 'naseem@sheeralateen.com', code: 'NASEEM', prefix: 'naseem' },
    { name: 'Palestine', email: 'palestine@sheeralateen.com', code: 'PALESTINE', prefix: 'palestine' },
    { name: 'Quwaizah', email: 'quwaizah@sheeralateen.com', code: 'QUWAIZAH', prefix: 'quwaizah' },
    { name: 'Almahameed', email: 'almahameed@sheeralateen.com', code: 'MAHAMEED', prefix: 'almahameed' },
    { name: 'Harazath', email: 'harazath@sheeralateen.com', code: 'HARAZATH', prefix: 'harazath' },
    { name: 'Taneem', email: 'taneem@sheeralateen.com', code: 'TANEEM', prefix: 'taneem' },
];

let sql = `-- ============================================================
-- Seed File: 16 Branch Managers
-- Passwords are set uniquely for each branch (e.g. naeem@321)
-- ============================================================

INSERT INTO users (name, email, password, role, branch_id, status) VALUES
`;

const values = [];

for (const b of branches) {
    const password = `${b.prefix}@321`;
    // Using cost factor 10 to match PHP password_hash default
    const hash = bcrypt.hashSync(password, 10);
    values.push(`('${b.name} Manager', '${b.email}', '${hash}', 'branch_manager', (SELECT id FROM branches WHERE branch_code = '${b.code}'), 'active')`);
}

sql += values.join(',\n') + ';\n\n';
sql += `-- Link the managers back to their branches
UPDATE branches b 
JOIN users u ON u.branch_id = b.id 
SET b.branch_manager_id = u.id 
WHERE u.role = 'branch_manager' AND b.branch_manager_id IS NULL;
`;

fs.writeFileSync('database/seed_managers.sql', sql);
console.log('Successfully generated database/seed_managers.sql with unique hashes.');
