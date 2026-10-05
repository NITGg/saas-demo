# local_nit_mlang — Multilingual fields

Turns every translatable field in the site into **one input per installed language
pack**, so nobody has to type multilang markup by hand.

Before:

```
Name  [ {mlang en}Certificate of Success{mlang}{mlang ar}شهادة نجاح{mlang} ]
```

After:

```
Name  ENGLISH (EN)   [ Certificate of Success ]
      العربية (AR)    [ شهادة نجاح            ]
```

The value that is submitted is unchanged — the plugin composes the `{mlang}`
markup itself — so **no server-side code, database column or report has to
change**, and the multilang filter keeps resolving it exactly as before.

## How languages are chosen

Never hard-coded. The list is the site's installed language packs
(*Site administration → Language → Language packs*), read through
`get_string_manager()->get_list_of_translations()`, ordered with the site default
language first. Each input gets the writing direction declared by its own pack, so
Arabic is RTL even on an English page. Install French and every enhanced field
becomes a three-language editor with no code change; if only one pack is
installed, the plugin does nothing at all.

## Two shapes

| Field | UI |
|-------|----|
| `<input type="text">` — Name, Full name, Title, Grade item name, Block title, Forum subject … | one labelled input per language, stacked |
| Rich text editor — Description, Summary, Intro, Question text … | a language tab strip above the editor; the editor holds one language at a time so the toolbar, file picker and HTML view keep working normally. A dot on a tab means that language has content. |
| Inline renaming — the pencil beside a section or activity name on the course page | one box per language in place of core's single box, with the language code as a badge beside each; Enter or *Save* saves both, Escape, *Cancel* or clicking away cancels |

## Which fields (`classes/registry.php`)

A field is translatable when its value is later printed through `format_string()`
or `format_text()` — that is where the filter runs. Moodle has no per-element flag
for this, so the plugin keeps a registry:

* **Text inputs are an allow list.** Most `<input type="text">` in Moodle hold
  identifiers, e-mails, numbers or URLs. Only a well-known set of names is a
  display string: `name`, `fullname`, `shortname`, `title`, `config_title`,
  `itemname`, `subject`, `option[…]`, `answer[…]`, and so on.
* **Editors are a deny list.** Practically every editor in Moodle holds
  `format_text()`-rendered prose, so they are all included except a few that hold
  code or templates (mod_data).

Exclusions are written `pagetype|fieldname` (both accept `*`), because the same
name can be a display string on one page and an identifier on another — `shortname`
is a course name on `course/edit.php` but a code on a custom-field form.

Both lists can be extended by an administrator in
*Site administration → Plugins → Local plugins → Multilingual fields*, so covering
a newly added field never needs a code change. The page type to use in a rule is
in the `<body>` class of the page.

## Inline renaming (`registry::INLINE_ITEMS`)

The pencil beside a section or activity name on the course page is not a form
field. It is a `core/inplace_editable`: core swaps the name for one `<input>`
that saves on Enter and cancels on blur, and calls the
`core_update_inplace_editable` web service with whatever was typed. Nothing in
the registry above can reach it, and one box cannot hold two languages.

So `fields.js` takes the click on the pencil away from core — in the capture
phase at the document, which is the only place that runs before core's
body-level handler — for the controls listed in `INLINE_ITEMS`, keyed by the
editable's `component|itemtype` pair:

```
format_*|sectionname       section name (course formats own it, hence the wildcard)
format_*|sectionnamenl     the same, in the "no link" header variant
core_course|activityname   activity name
```

The same `<span>` is then filled with one box per language, pre-filled by
parsing the stored value out of `data-value`. Saving composes the `{mlang}`
markup exactly as a form field would and hands it to core's own public
`getInplaceEditable().setValue()`, so the web service call, the re-render and
the `core/inplace_editable:updated` event the course editor listens for (it
refreshes its state from that) are all core's. Toggles, selects, autocompletes
and any text editable not in the list fall through to core untouched.

Two things worth knowing if it ever needs touching:

* The widget marks the span with the attributes core uses (`inplaceeditingon`,
  `data-oldcontent`, `data-inplace-in-draggable`), so either side can close a
  control the other opened, and dragging of the section/activity is paused while
  the boxes are open, as core does.
* Closing removes those markers *before* restoring the markup. Removing a
  focused box fires `focusout` synchronously, the blur-cancels handler
  re-enters the close routine, and if the markers were still there it would
  replace the content a second time under the first replacement — Chrome aborts
  that with a `NotFoundError` and the save never happens.

## Custom profile field *values* (`classes/profilefields.php`)

The registry above is about site content — a course name, an activity title. A
custom user profile field is different: whether its *value* is prose or an
identifier is data an administrator typed, not something a shipped list can know.
A specialisation and a biography want two languages; a passport number does not.

So that choice is made per **category**, in *Bilingual profile field categories* on
the settings page. Tick a category and every `text` ("Text input") field in it is
edited per language, on `/user/editadvanced.php`, on the account page, and anywhere
else the field appears. Text areas join them only when the second switch is on.
Other datatypes are left alone: a menu's options are translated on the field
definition, and a date or a file has no text.

These fields keep the ordinary two boxes, but **side by side** rather than one
under the other — the profile form was the one place asked for that layout, so it
is the one place that has it. The server sends the list as `inlinefields`, the JS
adds `nitml--inline` to those widgets and nothing else, and the boxes fold back
into a stack on a narrow screen. A text field outside that list is untouched.

Two things work differently here, both deliberately:

* **The capability does not gate it.** The field holds the person's own data, not
  the site's, so an instructor without `local/nit_mlang:edit` still gets the two
  boxes — but only on a profile screen (`profilefields::PAGETYPES`), and only for
  these fields. Nothing from the registry reaches them.
* **A `textarea` field is opt-in, and is enhanced even when "Include rich text
  editors" is off.** A ticked category covers its `text` ("Text input") fields
  only, because a text area gets a language tab strip rather than stacked boxes —
  a different control. Turn on *Include text areas in those categories* to cover
  a biography as well; the global editors switch is off site-wide for
  hand-authored HTML blocks, which is a different problem.

The category headings themselves are a separate matter — they are stored strings
like any other, and `local_profilefields\provision::repair_labels()` writes the
ones this academy uses as `{mlang}` pairs.

## Who sees it

Only holders of `local/nit_mlang:edit` — editing teachers, course creators and
managers by default. A student writing a forum post keeps the ordinary single
field. The one exception is a bilingual profile field, above.

## Where the seam is

Moodle exposes no hook for walking a `moodleform` definition, so the work happens
in the browser. `classes/hook/output_callbacks.php` only decides whether this
page/user qualifies and hands `amd/src/fields.js` the language list and the
registry. The module re-scans through a `MutationObserver`, so modal and dynamic
forms, repeated elements and anything else that arrives later are enhanced too.

No Moodle core file is touched.

## Known gaps

* A field whose combined value exceeds the column length can still be rejected by
  the server; the markup adds roughly 25 characters per language.

## Building the AMD module

`amd/build/fields.min.js` is a verbatim copy of `amd/src/fields.js` (the module is
written in plain AMD, so it needs no transpiling). After editing the source:

```bash
cp amd/src/fields.js amd/build/fields.min.js
```

then purge caches.
