# SonarQube + Semgrep CI/CD Trial — Findings

## TL;DR

Both tools work well together in GitHub Actions. SonarCloud is the right choice over self-hosted SonarQube for any team that doesn't have a compliance reason to self-host. Semgrep is free and tokenless but needs explicit rulepacks and correct job permissions to surface results in GitHub Security.

---

## SonarQube vs SonarCloud

**Use SonarCloud unless you have a reason not to.**

| | SonarQube (self-hosted) | SonarCloud (hosted) |
|---|---|---|
| Hosting | You run it | Sonarsource runs it |
| Cost | Free (Community Edition) | Free for public repos |
| PR decoration | Developer Edition+ (paid) | Included |
| Branch analysis | Developer Edition+ (paid) | Included |
| Setup effort | High — run a server | Low — OAuth with GitHub |
| CI integration | Needs scanner + server config | Actions action + token |

**Key limitation of Community Edition:** It only scans the main branch. No PR decoration, no branch comparison. You need Developer Edition (paid) or SonarCloud to get inline PR comments and per-branch analysis.

---

## SonarCloud Setup — What Actually Works

### Step 1 — Sign up
- sonarcloud.io → Log in with GitHub
- It will immediately run **Automatic Analysis** on your repo — this is not what you want for CI/CD

### Step 2 — Disable Automatic Analysis
This is non-obvious and easy to miss. Without doing this, SonarCloud and your Actions workflow will conflict and double-scan.
- Project → **Administration → Analysis Method** → turn off **Automatic Analysis**

### Step 3 — Generate a token
- Avatar → **My Account → Security** → Generate token
- Add to GitHub repo: **Settings → Secrets and variables → Actions** → `SONAR_TOKEN`

### Step 4 — Get your project key and org
- Project → **Information** (bottom left sidebar)
- You need both `Project Key` and `Organization Key` for `sonar-project.properties`

### sonar-project.properties (required at repo root)
```properties
sonar.projectKey=your-org_your-repo
sonar.organization=your-org
sonar.projectName=Your Project
sonar.projectVersion=1.0
sonar.sources=.
sonar.exclusions=**/.github/**
sonar.python.version=3
```

### GitHub Actions job
```yaml
sonarcloud:
  name: SonarCloud Scan
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
      with:
        fetch-depth: 0  # required — SonarCloud needs full history for blame/new-code
    - uses: SonarSource/sonarcloud-github-action@master
      env:
        GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
```

**`fetch-depth: 0` is required.** Without it, SonarCloud can't calculate new code or blame data correctly.

---

## Semgrep Setup — What Actually Works

### Free plan limitation
Semgrep's free plan does not give you an App Token. The token is required to use `--config=auto` (which fetches rules from the Semgrep registry) and to report findings to the Semgrep dashboard.

**Workaround:** Use explicit public rulepacks instead. No token needed, results still upload to GitHub Security via SARIF.

### `--config=auto` without a token = no rules
This is the key gotcha. `--config=auto` silently fetches a near-empty ruleset when unauthenticated. It will find nothing. Use explicit configs:

```
--config=p/python
--config=p/security-audit
--config=p/owasp-top-ten
--config=p/secrets
```

### `--error` blocks the SARIF upload
Using `--error` exits non-zero when findings are found, which kills the job before the upload step runs. Use `|| true` to let the job continue regardless:
```
semgrep scan ... || true
```

### Job needs `security-events: write` permission
Without this explicit permission on the job, the `upload-sarif` step silently fails and nothing appears in GitHub Security. This is not obvious from the error output.

```yaml
semgrep:
  name: Semgrep Scan
  runs-on: ubuntu-latest
  permissions:
    contents: read
    security-events: write  # required for SARIF upload to GitHub Security
    actions: read
```

### GitHub's generated Semgrep workflow requires a token
If you use GitHub's built-in **Security → Code scanning → Add tool → Semgrep** flow, it generates a workflow using `returntocorp/semgrep-action` which requires `SEMGREP_APP_TOKEN`. This won't work on the free plan. Use the manual approach above instead.

### Full working Semgrep job (no token)
```yaml
semgrep:
  name: Semgrep Scan
  runs-on: ubuntu-latest
  permissions:
    contents: read
    security-events: write
    actions: read
  container:
    image: semgrep/semgrep
  steps:
    - uses: actions/checkout@v4
    - name: Run Semgrep
      run: |
        semgrep scan \
          --config=p/python \
          --config=p/security-audit \
          --config=p/owasp-top-ten \
          --config=p/secrets \
          --sarif \
          --output=semgrep.sarif || true
    - name: Upload SARIF to GitHub Security
      uses: github/codeql-action/upload-sarif@v3
      if: always()
      with:
        sarif_file: semgrep.sarif
        category: semgrep
```

---

## What Each Tool Catches

| Issue | SonarCloud | Semgrep |
|---|---|---|
| SQL injection | Yes | Yes |
| Command injection | Yes | Yes |
| Hardcoded secrets | Yes | Yes (p/secrets) |
| Insecure deserialization (pickle) | Yes | Yes |
| Weak hashing (MD5 passwords) | Yes | Yes |
| Path traversal | Yes | Yes |
| Dead code / unused variables | Yes | No |
| Duplicate code blocks | Yes | No |
| Code smells / complexity | Yes | No |
| OWASP Top 10 | Partial | Yes (p/owasp-top-ten) |

SonarCloud is stronger on code quality and smells. Semgrep is stronger on targeted security rules and is more customisable. They complement each other well — use both.

---

## Quality Gate (blocking merges on bad code)

SonarCloud enforces a Quality Gate — a pass/fail threshold that can block PRs from merging.

- Default gate: fails if new code has any bugs, vulnerabilities, or security hotspots, or coverage drops below 80%
- Configure at: Project → **Administration → Quality Gate**
- To block PRs: in GitHub, set the SonarCloud check as a required status check under repo **Settings → Branches → Branch protection rules**

Semgrep with `--error` (or checking the job exit code) can also block merges — but you need to not use `|| true` and instead handle the SARIF upload separately.

---

## PR Decoration (inline comments on PRs)

- **SonarCloud:** Works out of the box on the free plan. Findings appear as inline comments on the PR diff.
- **Semgrep:** Findings appear in **GitHub Security → Code scanning** tab, not as inline PR comments. Inline comments require a paid Semgrep account.

---

## Gotchas Summary

| Gotcha | Fix |
|---|---|
| SonarCloud runs Automatic Analysis by default | Disable it in Administration → Analysis Method |
| `--config=auto` finds nothing without a token | Use explicit rulepacks (`p/python`, `p/secrets` etc.) |
| Semgrep SARIF upload silently fails | Add `security-events: write` to job permissions |
| `--error` kills job before upload | Use `\|\| true` on the scan command |
| GitHub's generated Semgrep workflow needs a paid token | Write your own job using the semgrep/semgrep container |
| SonarCloud `fetch-depth: 0` missing | Always set it — SonarCloud breaks without full git history |
| GitHub UI adds conflicting workflow files | Pull before pushing if you've edited workflows via GitHub UI |
