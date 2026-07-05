# Assessment Domain Model — Entity Relations

Reference diagram for `App\Assessment\Domain\Model\*` (see
[ADR-0012](adrs/0012-assessment-bounded-context.md) for the bounded-context
decision and [ADR-0014](adrs/0014-question-set-entities.md) for why these are
real Doctrine entities rather than page content). This doc only maps the
relations — composition vs. association vs. plain dependency — as a visual
supplement to the prose in those ADRs.

```
┌────────────────────────────┐
│   Sulu "Exercise" page      │   external, Sulu-owned content
│   (identified by pageUuid)  │
└──────────────┬─────────────┘
               │ association (anti-corruption port)
               │ ExerciseQuestionSetLocatorInterface::findQuestionSetId()
               ▼
┌────────────────────────────┐
│        QuestionSet         │  «aggregate root»
│  id, title                 │
└──────────────┬─────────────┘
               │ COMPOSITION  (1 ── *)
               │ OneToMany, cascade:['persist'], orphanRemoval:true
               │ "clear & rebuild" on save — items die with the set
               ▼
┌────────────────────────────┐
│       QuestionSetItem      │  «join / ordering entity»
│  position                  │
└──────────────┬─────────────┘
               │ ASSOCIATION (* ── 1)
               │ ManyToOne, NO cascade — references Question by identity
               │ (DB onDelete:CASCADE is referential-integrity only,
               │  not domain ownership)
               ▼
┌────────────────────────────┐
│          Question          │  «aggregate root, standalone & reusable»
│  id, text, explanation     │◄──── one Question can sit in many
└──────────────┬─────────────┘      QuestionSets, via many QuestionSetItems
               │ COMPOSITION  (1 ── *)
               │ OneToMany, cascade:['persist'], orphanRemoval:true
               │ "true composition" (per code comment):
               │ an Option cannot exist without its Question,
               │ and is never shared
               ▼
┌────────────────────────────┐
│           Option             │
│  text, isCorrect, position   │
└────────────────────────────┘


┌────────────────────────────┐        ┌────────────────────────────┐
│    SubmitAttemptService     │──uses──▶│           Grader             │  «domain service»
│  (Application layer)        │        │  grade(QuestionSet, answers) │
└──────────────┬─────────────┘        └──────────────┬────────────┘
               │ creates                              │ produces
               ▼                                      ▼
┌────────────────────────────┐        ┌────────────────────────────┐
│           Attempt            │        │        GradeResult           │
│  «standalone entity»         │        │  «value object, transient,   │
│  exerciseUuid, sessionId,    │        │   never persisted»           │
│  answers[], score, total     │        │  score, total, perQuestion[] │
└────────────────────────────┘        └────────────────────────────┘
     ▲
     │ DEPENDENCY (by UUID only — no FK, no navigable relation)
     │ links back to the Sulu Exercise page and, indirectly, the
     │ QuestionSet that was graded — deliberately decoupled
     └── exerciseUuid
```

## Relation legend

* **Composition** (`QuestionSet` → `QuestionSetItem`, `Question` → `Option`):
  `cascade: ['persist']` + `orphanRemoval: true` on the Doctrine mapping —
  the child's lifecycle is fully owned by the parent. Both cases are called
  out explicitly in the entities' own doc comments.
* **Association** (`QuestionSetItem` → `Question`): plain `ManyToOne`, no
  cascade — a reference by identity only. This is what makes `Question`
  reusable across multiple `QuestionSet`s (effectively a many-to-many with
  per-set ordering, realized through the join entity). The FK's
  `onDelete: 'CASCADE'` is a referential-integrity safety net, not domain
  ownership — see ADR-0014's Negative/Risks section for the resulting
  silent-delete caveat.
* **Dependency by identity only** (`Attempt.exerciseUuid`): no FK, no
  navigable object relation. `Attempt` deliberately has no live reference to
  `QuestionSet` — a graded attempt stays valid even if the `QuestionSet` is
  edited or deleted later.
* **Value object** (`GradeResult`): never persisted, constructed fresh per
  `Grader::grade()` call.
