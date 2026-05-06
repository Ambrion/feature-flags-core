## Семантика `PERCENTAGE N`

- `PERCENTAGE 100` + `value=true` → 100% пользователей получают `true`
- `PERCENTAGE 50` + `value=true` → ~50% получают `true`, остальные — `default`
- `PERCENTAGE 0` + `value=true` → 0% получают `true`, все — `default` (правило не применяется)

> 💡 Чтобы явно выключить флаг для всех: используйте `PERCENTAGE 100` + `value=false`.