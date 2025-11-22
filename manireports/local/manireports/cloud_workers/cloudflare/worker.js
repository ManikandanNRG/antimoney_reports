export default {
    async fetch(request, env, ctx) {
        // Only allow POST requests
        if (request.method !== 'POST') {
            return new Response('Method not allowed', { status: 405 });
        }

        try {
            const payload = await request.json();
            const { job_id, type, recipients } = payload;

            console.log(`Processing Job ID: ${job_id}, Type: ${type}, Recipients: ${recipients.length}`);

            const results = await processEmails(recipients, type, env);

            // Send callback to Moodle
            await sendCallback(job_id, results, env);

            return new Response(JSON.stringify({ success: true, results }), {
                headers: { 'Content-Type': 'application/json' },
            });

        } catch (error) {
            console.error('Worker Error:', error);
            return new Response(JSON.stringify({ success: false, error: error.message }), {
                status: 500,
                headers: { 'Content-Type': 'application/json' },
            });
        }
    },
};

async function processEmails(recipients, jobType, env) {
    let sentCount = 0;
    let failedCount = 0;
    const errors = [];

    for (const recipient of recipients) {
        try {
            const recipientData = JSON.parse(recipient.recipient_data);
            const { subject, body } = composeEmail(jobType, recipientData);

            // Use Cloudflare Email Routing or external provider (e.g. MailChannels, SendGrid via fetch)
            // Here we assume a generic sendEmail function that wraps the provider logic
            await sendEmail(recipient.email, subject, body, env);

            sentCount++;
        } catch (error) {
            console.error(`Failed to send to ${recipient.email}:`, error);
            failedCount++;
            errors.push(`${recipient.email}: ${error.message}`);
        }
    }

    return {
        status: failedCount === 0 ? 'completed' : 'partial_failure',
        emails_sent: sentCount,
        emails_failed: failedCount,
        errors: errors,
    };
}

function composeEmail(jobType, data) {
    let subject = 'Notification';
    let body = '<p>Notification</p>';

    if (jobType === 'user_created') {
        subject = 'Welcome to Our Platform';
        body = `
      <h1>Welcome, ${data.firstname}!</h1>
      <p>Your account has been created.</p>
      <p><strong>Username:</strong> ${data.username}</p>
      <p><strong>Password:</strong> ${data.password}</p>
      <p><a href="${data.loginurl}">Login Here</a></p>
    `;
    } else if (jobType === 'license_allocation') {
        subject = 'Course License Assigned';
        body = `
      <h1>Hello ${data.firstname}</h1>
      <p>You have been assigned a license for the course: <strong>${data.course_name}</strong></p>
      <p>License: ${data.license_name}</p>
    `;
    }

    return { subject, body };
}

async function sendEmail(to, subject, htmlBody, env) {
    // Example using MailChannels (common on Cloudflare Workers)
    // Or just a placeholder for the actual implementation

    if (!env.SEND_EMAIL_API_URL) {
        console.log(`[Mock Send] To: ${to}, Subject: ${subject}`);
        return;
    }

    const response = await fetch(env.SEND_EMAIL_API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${env.EMAIL_API_KEY}`
        },
        body: JSON.stringify({
            personalizations: [{ to: [{ email: to }] }],
            from: { email: env.SENDER_EMAIL },
            subject: subject,
            content: [{ type: 'text/html', value: htmlBody }]
        })
    });

    if (!response.ok) {
        throw new Error(`Email API error: ${response.statusText}`);
    }
}

async function sendCallback(jobId, results, env) {
    if (!env.MOODLE_CALLBACK_URL) {
        console.log('Skipping callback: MOODLE_CALLBACK_URL not set');
        return;
    }

    const payload = {
        job_id: jobId,
        status: results.status,
        emails_sent: results.emails_sent,
        emails_failed: results.emails_failed,
        errors: results.errors
    };

    try {
        const response = await fetch(env.MOODLE_CALLBACK_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${env.MOODLE_TOKEN}`
            },
            body: JSON.stringify(payload)
        });
        console.log(`Callback sent: ${response.status}`);
    } catch (error) {
        console.error('Failed to send callback:', error);
    }
}
