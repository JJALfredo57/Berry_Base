# CODEX SYSTEM DEVELOPMENT RULES

## 1. Understand the Request

- Basahin at intindihin muna ang request.
- Alamin ang expected result at limitations.
- Magtanong kapag may kulang o hindi malinaw.
- Huwag manghula sa importanteng requirements.

## 2. Inspect Before Editing

- Suriin muna ang existing code at project structure.
- Alamin kung paano konektado ang frontend, backend, database, routes, at APIs.
- Tukuyin ang exact files na kailangang baguhin.
- Huwag muna mag-edit habang nagsusuri.

## 3. Plan Before Coding

Bago mag-code, ipakita muna:

- Pagkakaintindi sa request
- Proposed solution
- Step-by-step implementation plan
- Mga file na babaguhin
- Expected behavior pagkatapos
- Posibleng epekto o risk sa existing system

## 4. Wait for Approval

- Huwag mag-edit, mag-delete, o mag-implement nang walang approval.
- Maghintay ng "Approved," "Proceed," o malinaw na pahintulot.
- Kapag may pinabago sa plan, i-revise ito at maghintay ulit ng approval.
- Maaari lamang lumaktaw rito kapag sinabi ng user na mag-implement agad.

## 5. Follow the Approved Scope

- Kung ano lang ang approved, iyon lang ang baguhin.
- Huwag galawin ang unrelated files, pages, features, o database tables.
- Huwag mag-refactor, mag-rename, o mag-install ng package nang walang dahilan at approval.
- Huwag alisin ang working feature.
- Kapag may nakitang ibang problema, i-report muna at huwag awtomatikong ayusin.

## 6. Professional UI/UX

- Gumawa ng malinis, modern, at consistent na design.
- Sundin ang existing colors, fonts, spacing, at component styles.
- Gumamit ng malinaw na labels, buttons, icons, at messages.
- Iwasan ang cluttered layout, sobrang kulay, at unnecessary animation.
- Maglagay ng loading, empty, success, warning, at error states kapag kailangan.
- Gawing madaling gamitin ang forms at navigation.

## 7. Mobile-First and Responsive Design

Ang UI ay dapat gumana sa:

- Mobile phones
- Tablets
- Laptops
- Desktop computers
- Large monitors

I-test sa karaniwang widths:

- 320px
- 375px
- 425px
- 768px
- 1024px
- 1366px
- 1440px
- 1920px

Siguraduhin na:

- Walang unwanted horizontal scrolling.
- Walang overlapping o napuputol na content.
- Responsive ang navigation, tables, forms, cards, images, at modals.
- Madaling pindutin ang buttons sa touchscreen.
- Hindi nakadepende sa hover ang importanteng actions.
- Readable ang text nang hindi kailangang mag-zoom.
- Hindi nawawala ang importanteng features sa mobile.

## 8. Clean Coding Rules

Ang code ay dapat:

- Malinaw at madaling maintindihan
- Modular at reusable
- Consistent sa existing project structure
- May descriptive names
- Madaling i-maintain at i-debug
- Walang unnecessary complexity

Iwasan ang:

- Duplicate code
- Unnecessary global variables
- Hard-coded values
- Malalaking functions na maraming responsibility
- Confusing nested conditions
- Pagpapalit ng buong file kung maliit na bahagi lang ang kailangang baguhin

## 9. Safe Logic

Isaalang-alang palagi ang:

- Empty at invalid inputs
- Duplicate submissions
- Missing database records
- Different user roles
- Network at server failures
- Existing data
- Edge cases
- Concurrent requests
- Possible effects sa ibang features

I-validate ang data sa frontend at backend. Huwag magtiwala sa client-side validation lamang.

## 10. Security Rules

- Gumamit ng authentication at role-based authorization.
- Gumamit ng ORM o prepared statements laban sa SQL injection.
- Protektahan laban sa XSS at CSRF.
- I-validate ang file uploads.
- I-hash nang secure ang passwords.
- Huwag ilagay sa source code ang API keys, passwords, at tokens.
- Huwag ipakita sa user ang raw database errors o stack traces.
- Huwag ilantad ang sensitive user information.

## 11. Database Safety

- Suriin muna ang existing schema at relationships.
- Huwag mag-delete o mag-rename ng tables at columns nang walang approval.
- Panatilihin ang existing records at foreign-key relationships.
- Gumamit ng transaction para sa magkakaugnay na operations.
- Ipaliwanag muna ang migration at rollback plan.
- Iwasan ang destructive database changes.

## 12. Error Handling

- Magpakita ng malinaw at user-friendly na error messages.
- Ihiwalay ang validation, network, at server errors.
- Mag-log ng technical errors nang ligtas.
- Huwag itago o i-ignore ang importanteng failures.
- Pigilan ang duplicate actions kapag loading o processing.

## 13. Testing and Verification

Pagkatapos mag-implement:

- I-review lahat ng binagong files.
- I-run ang relevant tests, build, lint, at formatter.
- I-test ang normal flow, invalid inputs, at edge cases.
- I-test ang bawat affected user role.
- I-check ang browser console at server logs.
- I-test sa mobile, tablet, at desktop.
- Siguraduhing walang nasirang existing feature.
- Huwag sabihing working kung hindi naman na-test.

## 14. Final Report

Pagkatapos ng trabaho, sabihin:

- Ano ang ginawa
- Anong files ang binago
- Anong logic o UI improvements ang inilagay
- Anong tests ang ginawa at resulta
- Kung may database o configuration steps
- Kung may limitations o kailangang gawin ng user

## Required Workflow

1. Understand the request
2. Inspect the existing system
3. Present the plan
4. Wait for approval
5. Implement only the approved changes
6. Test mobile and desktop
7. Verify existing features
8. Provide the final report
