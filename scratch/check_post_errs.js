const fs = require('fs');
const html = fs.readFileSync('scratch_post_result.html', 'utf8');
const errors = html.match(/id="id_error_[^"]*"/g);
console.log('Error divs on post result:');
for (const e of (errors || [])) {
    const id = e.match(/id="([^"]*)"/)[1];
    const m = html.match(new RegExp(`<[^>]*id="${id}"[^>]*>([\\s\\S]*?)<\\/`, 'i'));
    const content = m ? m[1].trim() : '';
    if (content) {
        console.log(id, 'has error:', JSON.stringify(content));
    }
}
