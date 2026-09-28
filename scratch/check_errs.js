const fs = require('fs');
const html = fs.readFileSync('scratch_signup.html', 'utf8');
const errors = html.match(/id="id_error_[^"]*"/g);
console.log('Error divs on initial clean form load:', errors);

// Also check textContent in those divs:
for (const e of (errors || [])) {
    const id = e.match(/id="([^"]*)"/)[1];
    const m = html.match(new RegExp(`<[^>]*id="${id}"[^>]*>([\\s\\S]*?)<\\/`, 'i'));
    console.log(id, 'content:', m ? JSON.stringify(m[1].trim()) : 'not found');
}
