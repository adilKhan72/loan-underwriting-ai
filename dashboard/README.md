# Underwriting dashboard

Vue 3 + TypeScript single-page app for underwriters, talking to the Laravel API (`../api`).

- Applications list with status filter; create an application, upload a paystub and bank statement, and submit it.
- Application view: masked applicant, document extractions (and what was redacted before the AI saw them),
  the decision with every rule result (problems first), the AI explanations, and the hash-verified audit timeline.
- Polls while documents are processing or an explanation is pending.

```bash
npm install
VITE_API_URL=http://localhost:8000 npm run dev
npm test        # vitest + @vue/test-utils
npm run build   # type-check (vue-tsc) + production build
```

The API token is kept in `sessionStorage`, so it's cleared when the tab closes.
