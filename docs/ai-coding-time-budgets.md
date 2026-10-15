# AI execution budgets

Low, High and Max time budgets are doubled for both Eco and Default. Off and output-token budgets are unchanged. Coding and science budgets are measured from the original run start, shared by all scripts and final generation.

| Effort | Chat total | Chat request max | Coding/science total | Coding/science request max |
| --- | ---: | ---: | ---: | ---: |
| Off | 60s | 30s | 180s | 120s |
| Low | 180s | 90s | 480s | 360s |
| High | 360s | 150s | 720s | 480s |
| Max | 600s | 300s | 960s | 720s |

Every request remains capped by the remaining run budget, with five seconds reserved. Repeated delegations never reset the clock. Cancellation, bounded provider retries and lease recovery remain unchanged. These limits do not guarantee provider completion.

AI jobs and the worker command allow 990s. Database, Redis and Beanstalkd reservations enforce a 1020s minimum even with older environment overrides. Set SQS visibility to at least 1020s separately. Browser observation allows 1020s without extending execution deadlines. Local script execution sandbox limits are unchanged.

After deployment, rebuild cached configuration if used, rebuild frontend assets and restart persistent workers. Already serialized jobs may retain their previous timeout; new limits apply to newly queued jobs. No database migration is needed.
