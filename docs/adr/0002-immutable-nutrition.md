# ADR 0002: Immutable nutrition snapshots

Every logged consumption will retain ingredient identity, quantity, basis, portions and calculated nutrient values as a snapshot. Food and recipe edits will affect new entries only. Unknown macro values stay unknown, including legacy workbook rows with only calories and protein. Per-food conversions are explicit; no density is inferred.
