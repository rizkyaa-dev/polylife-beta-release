# Coding execution budgets

Reasoning effort is still passed unchanged to the provider. Ordinary conversation
budgets remain unchanged. Only a validated coding delegation promotes the run to
the coding budget, measured from the original monotonic run start:

| Effort | Coder request maximum | Total coding run maximum |
| --- | ---: | ---: |
| Off | 120s | 180s |
| Low | 180s | 240s |
| High | 240s | 360s |
| Max | 360s | 480s |

Every request remains capped by the remaining run budget, reserving five seconds
for completion. Repeated delegations never reset the run clock. Cancellation,
bounded provider retries and lease-based recovery remain in place. These are
initial operational limits, not measured latency guarantees.

The AI job and worker command allow 510s. Database, Redis and Beanstalkd queue
reservation durations have a 540s minimum, including when an old environment
override is present. This replaces the older 330s/420s deployment guidance.
SQS visibility timeout is managed externally: configure it above 510s before
using SQS for these jobs. Browser polling does not extend the execution deadline.

After deployment, rebuild cached configuration if used and restart supervised
AI workers so they load the new PHP code and reservation settings. No frontend
build or database migration is needed. Jobs already serialized in the queue may
retain their old job timeout; the new limits apply to newly queued jobs.

Verification: `php artisan test tests/Unit/Ai tests/Feature/Ai`.
