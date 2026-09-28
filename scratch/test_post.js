const https = require('https');
const fs = require('fs');

// First fetch the signup page to get fresh sesskey and cookies
function get(url) {
    return new Promise((resolve, reject) => {
        https.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ headers: res.headers, body: data, statusCode: res.statusCode }));
        }).on('error', reject);
    });
}

function post(url, postData, cookies) {
    return new Promise((resolve, reject) => {
        const u = new URL(url);
        const options = {
            hostname: u.hostname,
            path: u.pathname,
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': Buffer.byteLength(postData),
                'Cookie': cookies,
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
            }
        };
        const req = https.request(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ headers: res.headers, body: data, statusCode: res.statusCode }));
        });
        req.on('error', reject);
        req.write(postData);
        req.end();
    });
}

async function test() {
    console.log('1. Fetching signup page...');
    const res1 = await get('https://abdo2.academy2026.nitg-eg.com/login/signup.php');
    const cookieHeader = res1.headers['set-cookie'];
    const cookies = cookieHeader ? cookieHeader.map(c => c.split(';')[0]).join('; ') : '';
    console.log('Cookies:', cookies);

    const sesskeyMatch = res1.body.match(/name="sesskey"[^>]*value="([^"]+)"/) || res1.body.match(/value="([^"]+)"[^>]*name="sesskey"/);
    const sesskey = sesskeyMatch ? sesskeyMatch[1] : '';
    console.log('Sesskey:', sesskey);

    const params = new URLSearchParams({
        'sesskey': sesskey,
        '_qf__login_signup_form': '1',
        'mform_isexpanded_id_category_1': '1',
        'username': 'TestStudent', // Invalid: capital letters!
        'password': 'Password123!',
        'email': 'teststudent8877@gmail.com',
        'email2': 'teststudent8877@gmail.com',
        'firstname': 'Ahmed',
        'lastname': 'Ali',
        'city': 'Cairo',
        'country': 'EG',
        'profile_field_parentphone': '01012345678',
        'submitbutton': 'Create account'
    });

    console.log('2. Submitting POST...');
    const res2 = await post('https://abdo2.academy2026.nitg-eg.com/login/signup.php', params.toString(), cookies);
    console.log('POST status code:', res2.statusCode);
    if (res2.headers.location) {
        console.log('Redirect location:', res2.headers.location);
    }
    fs.writeFileSync('scratch_post_result.html', res2.body, 'utf8');
    
    // Check if error message is present in response
    const hasError = res2.body.includes('error') || res2.body.includes('alert') || res2.body.includes('invalid');
    console.log('Result body length:', res2.body.length);
    console.log('Contains error/alert:', hasError);
    
    // Search for notification or errors
    const errorMatches = [...res2.body.matchAll(/<span[^>]*class="[^"]*error[^"]*"[^>]*>([\s\S]*?)<\/span>/gi)].map(m => m[1]);
    const feedbackMatches = [...res2.body.matchAll(/class="[^"]*(?:invalid-feedback|error)[^"]*"[^>]*>([\s\S]*?)<\/div>/gi)].map(m => m[1]);
    const notifications = [...res2.body.matchAll(/class="[^"]*(?:alert|notification)[^"]*"[^>]*>([\s\S]*?)<\/div>/gi)].map(m => m[1]);
    console.log('Errors found:', errorMatches);
    console.log('Feedback found:', feedbackMatches);
    console.log('Notifications:', notifications);
}

test().catch(console.error);
