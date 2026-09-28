const fs = require('fs');

const html = fs.readFileSync('scratch_signup.html', 'utf8');

// Let's check what script runs in signup_form_layout:
// Look at what happens to rows in signup_form_layout:
// var rows = Array.prototype.slice.call(form.querySelectorAll('.fitem, .form-group.row'))
// .filter(...)

// Let's check all occurrences of .fitem and .form-group in scratch_signup.html
const fitems = html.match(/class="[^"]*(fitem|form-group)[^"]*"/g);
console.log('Total fitem/form-group found:', fitems ? fitems.length : 0);

// Let's check if the submit button has an onclick or form has onsubmit
const onsubmitMatch = html.match(/<form[^>]*onsubmit="([^"]*)"/i);
console.log('Form onsubmit attribute:', onsubmitMatch ? onsubmitMatch[1] : 'NONE');

// Let's check any inline scripts for validation
const scripts = html.match(/<script[\s\S]*?<\/script>/gi);
console.log('Total script tags:', scripts ? scripts.length : 0);
for (const s of scripts) {
    if (s.includes('validate') || s.includes('signup') || s.includes('skipClientValidation')) {
        console.log('Relevant script snippet:\n', s.slice(0, 300));
    }
}
