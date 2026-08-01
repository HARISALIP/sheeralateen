const fs = require('fs');

const html = fs.readFileSync('SHEERALATEEN Official_ Linktree.html', 'utf8');
const regex = /<a[^>]+href="([^"]+)"[^>]*>([\s\S]*?)<\/a>/gi;
let match;
const links = [];

while ((match = regex.exec(html)) !== null) {
    let href = match[1];
    let text = match[2].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (text && href.includes('maps') || text.includes('Sheera') || text.includes('Lateen')) {
        links.push({ href, text });
    }
}

console.log(JSON.stringify(links, null, 2));
