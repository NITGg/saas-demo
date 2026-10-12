<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'Anthropic API key';
$string['apikey_desc'] = 'This academy\'s own key from console.anthropic.com (API keys). Stored encrypted; leave the dots to keep the saved key. Set a monthly spend limit in the Anthropic console as a backstop.';
$string['chatprofile'] = 'Chat model';
$string['chatprofile_desc'] = 'Standard suits most academies. Complex is smarter and about twice the price; fast is cheapest.';
$string['dailybudget'] = 'Daily budget (USD)';
$string['dailybudget_desc'] = 'The assistant stops for the rest of the day when this is reached and visitors see the contact options instead. 0 keeps it off.';
$string['enabled'] = 'Assistant on';
$string['enabled_desc'] = 'Shows the chat on the site and in the app. It also needs an API key and a daily budget above 0.';
$string['error_busy'] = 'The assistant is busy; please try again.';
$string['error_model'] = 'The AI service could not be reached.';
$string['error_nokey'] = 'No Anthropic API key is set for this academy.';
$string['msg_busy'] = 'A reply is still being written — one moment.';
$string['msg_fallback'] = 'Sorry, the assistant is not available right now. Please reach us through the contact options on this page.';
$string['msg_turnlimit'] = 'Sorry, I couldn\'t complete that answer. Would you like me to connect you with a team member?';
$string['nit_assistant:manage'] = 'Manage the AI assistant';
$string['nit_assistant:reply'] = 'Take over AI conversations and reply';
$string['nit_assistant:use'] = 'Chat with the AI assistant';
$string['nit_assistant:viewinbox'] = 'See the AI Inbox and get handoff alerts';
$string['pluginname'] = 'AI assistant';
$string['privacy:conversations'] = 'AI assistant conversations';
$string['privacy:metadata:anthropic'] = 'Messages are sent to Anthropic (Claude) to write the assistant\'s replies.';
$string['privacy:metadata:anthropic:messages'] = 'The conversation text and the account data the assistant looked up to answer.';
$string['privacy:metadata:audit'] = 'Tool calls the assistant made for the user (inputs masked).';
$string['privacy:metadata:audit:tool'] = 'Which tool ran.';
$string['privacy:metadata:audit:userid'] = 'The user the tool ran for.';
$string['privacy:metadata:conv'] = 'Conversations with the AI assistant.';
$string['privacy:metadata:conv:lang'] = 'The language of the conversation.';
$string['privacy:metadata:conv:summary'] = 'An AI-written summary of the conversation.';
$string['privacy:metadata:conv:timecreated'] = 'When the conversation started.';
$string['privacy:metadata:conv:userid'] = 'The user who chatted.';
$string['privacy:metadata:event'] = 'Cost of each model call, per user.';
$string['privacy:metadata:event:cost'] = 'The estimated cost in USD.';
$string['privacy:metadata:event:userid'] = 'The user the call was for.';
$string['privacy:metadata:msg'] = 'Messages in a conversation.';
$string['privacy:metadata:msg:content'] = 'The text of the message.';
$string['privacy:metadata:msg:timecreated'] = 'When the message was sent.';
$string['profile_complex'] = 'Complex (Claude Opus 5.5)';
$string['profile_fast'] = 'Fast (Claude Haiku 5.5)';
$string['profile_standard'] = 'Standard (Claude Sonnet 5.5)';
$string['secretnocrypto'] = 'Encryption is not configured ($CFG->nit_crypto_key), so the key cannot be stored. Ask the server admin to set it.';
$string['secretsaved'] = 'A key is saved (encrypted).';
$string['settings'] = 'AI assistant settings';
$string['summaryprofile'] = 'Summary model';
$string['summaryprofile_desc'] = 'Used for conversation summaries and tags.';
