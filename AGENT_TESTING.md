# Agent Testing Rules

**How to install:** commit this file to the repo root as `AGENT_TESTING.md`, then add one
line to your `CLAUDE.md` (create it at the repo root if you don't have one):

```markdown
Always follow the testing rules in `AGENT_TESTING.md`. Before finishing any task,
verify every applicable rule has been satisfied. If a rule cannot be satisfied,
say so explicitly instead of skipping it silently.
```

Commit both files. Every teammate's Claude Code then picks them up automatically —
nobody has to remember to ask.

---

## The rule

Every change to behaviour ships with a test that covers it, and the tests are **run**
before the work is called done. Not "tests can be added later" — the test is part of
the change, in the same commit.

This is not about coverage percentages. It is about one thing: **the next person to
touch this code, human or agent, needs a way to find out they broke it.**

---

## 1. What needs a test

Write a test when the change:

- adds or changes an endpoint, handler, route, or command
- adds or changes business logic — pricing, permissions, validation, state transitions,
  parsing, formatting, date/timezone handling
- fixes a bug (**the test reproduces the bug first** — see §3)
- adds or changes a UI component's behaviour — what it renders in each state, what it
  does when clicked, what it does when the request fails
- changes an error path, a retry, a timeout, or a fallback
- changes anything about auth, ownership, or tenancy

## 2. What does NOT need a test

Do not pad the suite. These are noise, and noise gets ignored:

- pure renames, formatting, comments, import reordering
- config, styling, copy changes with no logic in them
- generated code
- thin pass-through wrappers that contain no decisions
- third-party library behaviour — test *your* use of it, not the library
- getters/setters and plain data objects

If a change genuinely needs no test, say which of these it falls under. Don't leave it
unmentioned.

---

## 3. The test must be able to fail

This is the rule that matters most, and the one most often broken by agent-written tests.

**Before you believe a test, break the code and watch the test go red.** Comment out the
fix, flip the condition, return the wrong value — then run it. If the test still passes,
it is not testing anything and it is worse than no test, because it looks like coverage.

Then restore the code and confirm it goes green again.

For a bug fix, do this in order:

1. Write the failing test first. Run it. **It must fail, for the reason the bug exists.**
2. Apply the fix.
3. Run it. It passes.

A test written after the fix, never seen failing, is a guess.

---

## 4. Test behaviour, not wiring

Common failure modes to avoid:

- **Testing the mock.** If every dependency is stubbed, you have asserted that your stubs
  return what you told them to. Mock what is slow, external, or non-deterministic — the
  network, the clock, the payment provider. Do not mock the thing under test, and prefer a
  real in-memory database or a test transaction over a mocked repository.
- **Asserting on internals.** Don't assert that a private method was called N times.
  Assert on what comes out: the response body, the status code, the rendered text, the
  saved row, the emitted event. A refactor that keeps behaviour identical must not turn
  the suite red.
- **Recording snapshots.** A snapshot test written from current output just freezes today's
  behaviour, bug included, and gets `--update`d the moment it complains. Use them sparingly
  and never for logic.
- **Happy path only.** The interesting cases are the empty list, the missing field, the
  expired token, the duplicate submit, the 500 from upstream, the RTL/long string, the
  wrong user asking. Cover at least one failure path per change.

---

## 5. Run them, and report honestly

- Run the **focused** tests while iterating (single file / single test), the **full suite**
  before saying the task is done.
- Never state that tests pass without having run them in this session. Paste the runner's
  actual summary line.
- If a test fails, **fix the code or fix the test's expectation — whichever is actually
  wrong.** Never make it green by deleting it, skipping it, marking it `xfail`/`.skip`,
  loosening an assertion to something trivially true, or widening a tolerance until it fits.
- If a test was already failing before your change, say so and say whether it is related.
  Don't silently inherit a red suite, and don't fix unrelated failures without mentioning it.
- Lint/typecheck counts too. Run the project's linter and type checker and finish at zero
  issues.

---

## 6. If the repo has no tests yet

**Do not stop and build a test suite for the whole codebase.** Nobody asked for that, and
it will bury the change you were asked to make.

On the first change, do exactly this and no more:

1. Install the **standard, boring** test runner for the stack (see the table below). Not an
   exotic one. Not a custom harness.
2. Add the smallest possible config, plus a `test` script/target so there is one obvious
   command — `npm test`, `pytest`, `go test ./...`.
3. Write **one** test: the one covering the change you were already making. Make it a real
   test, per §3 and §4.
4. Run it. Green.
5. Tell the user in one line that you set up the harness, what command runs it, and that
   coverage will grow one change at a time.

Then every subsequent change adds its own test. The suite grows by accretion, and within a
few weeks the paths people actually touch are the paths that are covered — which is the
coverage that matters.

**Two exceptions, where you should ask first rather than proceed:**

- The harness needs infrastructure the repo doesn't have (a test database, containers, a
  seeded fixture set, CI secrets). Write the test file anyway, explain what it needs to
  run, and ask before adding infrastructure.
- The code is untestable as written — a 600-line function reaching straight into globals
  and the network. Say so, propose the smallest seam that would make it testable, and let
  the user decide whether to refactor now or defer.

Never invent a fake in-house assertion helper because the real runner "seemed like
overkill". Use what the ecosystem uses.

---

## 7. Stack cheat-sheet

Use the project's existing runner if it has one. If not, these are the defaults:

| Stack | Runner | Run all | Run one |
|---|---|---|---|
| Node / TypeScript | Vitest (Jest if already present) | `npm test` | `npx vitest run path/to/file.test.ts` |
| React / Vue / Svelte | Vitest + Testing Library + jsdom | `npm test` | `npx vitest run src/Thing.test.tsx` |
| Python | pytest | `pytest -q` | `pytest path/test_x.py::test_name` |
| Django | pytest + pytest-django | `pytest -q` | `pytest app/tests/test_x.py -k name` |
| PHP / Laravel | Pest or PHPUnit | `php artisan test` | `php artisan test --filter=TestName` |
| Go | stdlib `testing` | `go test ./...` | `go test ./pkg -run TestName` |
| Ruby / Rails | RSpec | `bundle exec rspec` | `bundle exec rspec spec/x_spec.rb:42` |
| Java / Spring | JUnit 5 | `./mvnw test` | `./mvnw test -Dtest=ClassName` |
| .NET | xUnit | `dotnet test` | `dotnet test --filter Name~X` |
| Flutter / Dart | `flutter_test` | `flutter test` | `flutter test test/x_test.dart` |

**Web front-end specifics:**

- Query the way a user finds things — by role, label, and visible text
  (`getByRole('button', { name: 'Save' })`). Reach for `data-testid` only when there is
  genuinely no accessible handle; if there isn't one, that is usually an accessibility bug
  worth fixing instead.
- Test the component's **states**: loading, empty, error, populated, disabled, and the
  interaction that moves between them.
- End-to-end tests (Playwright/Cypress) are not a substitute for component tests. Keep a
  thin E2E layer over the two or three critical journeys; everything else is faster and
  more precise one level down.

**Backend specifics:**

- Test the HTTP layer through the framework's test client, not by calling the handler
  function directly — the middleware, serialisation, and status codes are part of the
  behaviour.
- Every endpoint that has an owner or a role gets a test that the **wrong** user is
  refused. Authorization bugs are silent in manual testing and expensive in production.
- Use a transaction-per-test or a throwaway schema. Tests must not depend on each other's
  leftovers, and must pass when run in any order.

---

## 8. Definition of done

A task is not finished until all of these are true and stated:

- [ ] Behaviour changed → a test covers it, in the same commit.
- [ ] Bug fixed → the test was seen failing before the fix.
- [ ] The test was proven capable of failing (§3).
- [ ] Failure paths, not just the happy path.
- [ ] Full suite run in this session, and green — with the summary line quoted.
- [ ] Linter and type checker clean.
- [ ] Nothing was skipped, deleted, or weakened to get there.
- [ ] Anything not done is named explicitly, with the reason.

---

## 9. Optional: stop the permission prompts

So Claude can run the suite without asking every time, add the runner to
`.claude/settings.json` in the repo (commit it — it applies to the whole team):

```json
{
  "permissions": {
    "allow": [
      "Bash(npm test:*)",
      "Bash(npx vitest:*)",
      "Bash(pytest:*)",
      "Bash(go test:*)",
      "Bash(php artisan test:*)"
    ]
  }
}
```

Keep only the lines that match your stack.

## 10. Optional but recommended: make CI enforce it

Rules in a file are a convention; CI is the thing that actually holds. A minimal GitHub
Actions workflow that runs the suite on every pull request means a change without tests
gets caught even when nobody was watching the agent.
