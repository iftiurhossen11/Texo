# No-Answer Test Case

Use this question when **no document covering this topic** has been uploaded to the live demo index.

## Test question
**What is the capital city of Mars and its official textile export policy?**

## Expected system behaviour
- Returns honest "not found" message (English + Bangla)
- **No LLM call** — retrieval returns empty below 0.35 cosine threshold
- UI shows empty retrieved-context panel, not an error crash

## Calibration pair
| Case | Should retrieve? |
|---|---|
| Primary Q: broken stitch | YES |
| This no-answer question | NO |
