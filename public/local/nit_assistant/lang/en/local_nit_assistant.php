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

$string['action_buycourse'] = 'Buy this course';
$string['action_buypackage'] = 'Buy a lesson package';
$string['action_catalogue'] = 'See the courses';
$string['action_course'] = 'Open: {$a}';
$string['action_enrolfree'] = 'Start this free course';
$string['action_joinlesson'] = 'Join the lesson with {$a}';
$string['action_joinsession'] = 'Join: {$a}';
$string['action_mycourses'] = 'My courses';
$string['action_myflex'] = 'My lesson credits';
$string['action_mylessons'] = 'My lessons';
$string['action_mysubs'] = 'My subscription';
$string['action_openlesson'] = 'Open: {$a}';
$string['action_packages'] = 'See the lesson packages';
$string['action_payments'] = 'My payments';
$string['action_plans'] = 'See the plans';
$string['action_signup'] = 'Create an account';
$string['action_subscribe'] = 'Subscribe';
$string['action_teacher'] = 'Teacher: {$a}';
$string['action_wallet'] = 'My wallet';
$string['alert_handoff'] = 'AI chat #{$a} needs a person';
$string['alert_handoff_body'] = 'The assistant handed conversation #{$a} to the team. Open the AI Inbox to reply.';
$string['alert_lead'] = 'New lead from AI chat #{$a}';
$string['alert_lead_body'] = 'A visitor left their details in conversation #{$a} and agreed to be contacted.';
$string['alert_message'] = 'New message in AI chat #{$a}';
$string['alert_message_body'] = 'The user wrote again in conversation #{$a}, which is waiting for the team.';
$string['alert_ticket'] = 'New support ticket from AI chat #{$a}';
$string['alert_ticket_body'] = 'A student opened a support ticket in conversation #{$a}. Open Tickets to follow it up.';
$string['apikey'] = 'Anthropic API key';
$string['apikey_desc'] = 'This academy\'s own key from console.anthropic.com (API keys). Stored encrypted; leave the dots to keep the saved key. Set a monthly spend limit in the Anthropic console as a backstop.';
$string['assistant'] = 'Assistant';
$string['cachedef_help'] = 'The academy\'s help pages as text for the assistant';
$string['chat_ai_note'] = 'AI assistant — it can make mistakes. You can ask for a person at any time.';
$string['chat_close'] = 'Close the chat';
$string['chat_error_busy'] = 'Still answering your last message — one moment.';
$string['chat_error_full'] = 'This conversation is long. Start a new one with the + button.';
$string['chat_error_generic'] = 'Something went wrong. Please try again.';
$string['chat_error_ratelimited'] = 'You have sent many messages. Please wait a little and try again.';
$string['chat_error_session'] = 'Your session has expired. Please reload the page.';
$string['chat_human'] = 'A team member is with you in this chat.';
$string['chat_new'] = 'New conversation';
$string['chat_open'] = 'Chat with us';
$string['chat_placeholder'] = 'Write your message…';
$string['chat_send'] = 'Send';
$string['chat_team'] = 'Team';
$string['chat_thinking'] = 'Typing…';
$string['chat_title'] = 'Ask us';
$string['chat_waiting'] = 'Your message is with the team. They will reply here.';
$string['chat_you'] = 'You';
$string['chatheading'] = 'The chat';
$string['chatprofile'] = 'Chat model';
$string['chatprofile_desc'] = 'Standard suits most academies. Complex is smarter and about twice the price; fast is cheapest.';
$string['closeconv'] = 'Close the conversation';
$string['col_account'] = 'Account';
$string['col_cacheread'] = 'Cached tokens';
$string['col_calls'] = 'Calls';
$string['col_contact'] = 'Contact';
$string['col_cost'] = 'Cost (USD)';
$string['col_day'] = 'Day';
$string['col_interest'] = 'Wants';
$string['col_lang'] = 'Language';
$string['col_last'] = 'Last message';
$string['col_messages'] = 'Messages';
$string['col_model'] = 'Model';
$string['col_note'] = 'Note for the team';
$string['col_page'] = 'Started on page';
$string['col_problem'] = 'Problem';
$string['col_started'] = 'Started';
$string['col_status'] = 'Status';
$string['col_tokensin'] = 'Input tokens';
$string['col_tokensout'] = 'Output tokens';
$string['col_when'] = 'When';
$string['col_who'] = 'Who';
$string['dailybudget'] = 'Daily budget (USD)';
$string['dailybudget_desc'] = 'The assistant stops for the rest of the day when this is reached and visitors see the contact options instead. 0 keeps it off.';
$string['enabled'] = 'Assistant on';
$string['enabled_desc'] = 'Shows the chat on the site and in the app. It also needs an API key and a daily budget above 0.';
$string['error_busy'] = 'The assistant is busy; please try again.';
$string['error_changed'] = 'The conversation changed meanwhile (someone else may have acted). Check it and try again.';
$string['error_model'] = 'The AI service could not be reached.';
$string['error_nokey'] = 'No Anthropic API key is set for this academy.';
$string['error_notowner'] = 'Another team member has this conversation.';
$string['error_reply'] = 'Write a reply (up to 4000 characters).';
$string['filter_active'] = 'Active';
$string['greeting'] = 'Greeting';
$string['greeting_default'] = 'Hello! 👋 I\'m the academy\'s assistant. Ask me about courses, plans, prices or live lessons.';
$string['greeting_desc'] = 'The first message of the chat. For both languages write {mlang en}…{mlang}{mlang ar}…{mlang}. Empty uses a standard greeting.';
$string['handback'] = 'Hand back to the assistant';
$string['hourlylimit'] = 'Messages per hour per person';
$string['hourlylimit_desc'] = 'Protects the budget from abuse: a visitor (by IP) or user can send this many messages an hour, and four times as many a day.';
$string['inbox'] = 'AI Inbox';
$string['inbox_empty'] = 'No conversations here.';
$string['lead'] = 'Lead';
$string['leads'] = 'Leads';
$string['leads_empty'] = 'No leads yet.';
$string['leadstatus_contacted'] = 'Contacted';
$string['leadstatus_lost'] = 'Lost';
$string['leadstatus_new'] = 'New';
$string['leadstatus_won'] = 'Won';
$string['messageprovider:staffalert'] = 'AI assistant: chats that need the team, and new leads';
$string['msg_busy'] = 'A reply is still being written — one moment.';
$string['msg_fallback'] = 'Sorry, the assistant is not available right now. Please reach us through the contact options on this page.';
$string['msg_handoff'] = 'I\'ve passed your message to our team; a team member will reply here in this chat.';
$string['msg_handoff_hours'] = 'Team hours: {$a}.';
$string['msg_turnlimit'] = 'Sorry, I couldn\'t complete that answer. Would you like me to connect you with a team member?';
$string['nit_assistant:manage'] = 'Manage the AI assistant';
$string['nit_assistant:reply'] = 'Take over AI conversations and reply';
$string['nit_assistant:use'] = 'Chat with the AI assistant';
$string['nit_assistant:viewinbox'] = 'See the AI Inbox and get handoff alerts';
$string['notes'] = 'Notes for the assistant';
$string['notes_desc'] = 'Facts the assistant may use that are not on the site: payment methods, refund rules, how to reach you, common questions. One topic per paragraph (blank line between). It quotes only what is written here and on the site.';
$string['openconversation'] = 'Open the conversation';
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
$string['privacy:metadata:lead'] = 'Contact details a visitor gave the assistant so the team can call them back.';
$string['privacy:metadata:lead:email'] = 'Their email.';
$string['privacy:metadata:lead:interest'] = 'What they were interested in.';
$string['privacy:metadata:lead:name'] = 'Their name.';
$string['privacy:metadata:lead:phone'] = 'Their phone number.';
$string['privacy:metadata:lead:userid'] = 'The user, if they were signed in.';
$string['privacy:metadata:msg'] = 'Messages in a conversation.';
$string['privacy:metadata:msg:content'] = 'The text of the message.';
$string['privacy:metadata:msg:timecreated'] = 'When the message was sent.';
$string['privacy:metadata:ticket'] = 'Support tickets a student opened through the assistant.';
$string['privacy:metadata:ticket:description'] = 'The problem as written up in the chat.';
$string['privacy:metadata:ticket:subject'] = 'The ticket\'s subject.';
$string['privacy:metadata:ticket:userid'] = 'The student who opened it.';
$string['profile_complex'] = 'Complex (Claude Opus 5.5)';
$string['profile_fast'] = 'Fast (Claude Haiku 5.5)';
$string['profile_standard'] = 'Standard (Claude Sonnet 5.5)';
$string['reply'] = 'Reply';
$string['reply_takes_over'] = 'Replying takes the conversation over: the assistant stops answering until you hand it back.';
$string['secretnocrypto'] = 'Encryption is not configured ($CFG->nit_crypto_key), so the key cannot be stored. Ask the server admin to set it.';
$string['secretsaved'] = 'A key is saved (encrypted).';
$string['settings'] = 'AI assistant settings';
$string['staffnotes'] = 'Team notes';
$string['status_closed'] = 'Closed';
$string['status_human'] = 'With a person';
$string['status_open'] = 'Assistant';
$string['status_waiting_human'] = 'Waiting for a person';
$string['summaryprofile'] = 'Summary model';
$string['summaryprofile_desc'] = 'Used for conversation summaries and tags.';
$string['takeover'] = 'Take over';
$string['task_cleanup'] = 'Clean up AI assistant counters';
$string['ticketcat_account'] = 'Account';
$string['ticketcat_billing'] = 'Payment';
$string['ticketcat_lesson'] = 'Lesson';
$string['ticketcat_other'] = 'Other';
$string['ticketcat_technical'] = 'Technical';
$string['tickets'] = 'Tickets';
$string['tickets_empty'] = 'No tickets here.';
$string['ticketstatus_closed'] = 'Closed';
$string['ticketstatus_in_progress'] = 'In progress';
$string['ticketstatus_open'] = 'Open';
$string['ticketstatus_resolved'] = 'Resolved';
$string['usage'] = 'Usage & cost';
$string['usage_30days'] = 'Last 30 days';
$string['usage_conversations'] = 'Conversations (30 days)';
$string['usage_estimate'] = 'Costs are estimated from token counts and the price table; your Anthropic console shows the exact bill.';
$string['usage_handoffs'] = 'Handed to the team (30 days)';
$string['usage_leads'] = 'Leads (30 days)';
$string['usage_none'] = 'No usage yet.';
$string['usage_today'] = 'Today (spent / budget)';
$string['visitor'] = 'Visitor #{$a}';
$string['visitors'] = 'Visitors can chat';
$string['visitors_desc'] = 'Show the chat to people who are not signed in (the sales assistant).';
$string['workinghours'] = 'Team working hours';
$string['workinghours_desc'] = 'Told to people handed to the team, e.g. "Sun–Thu 10:00–18:00". Use {mlang} for two languages.';
