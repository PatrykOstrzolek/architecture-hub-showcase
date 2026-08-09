/**
 * `isCorrect` is never present — the QuestionSetSelectionPropertyResolver
 * deliberately never includes it in headless output (see ADR-0014), so
 * there's nothing to redact after the fact like the old
 * ExerciseAnswerRedactionSubscriber had to. A Question can have zero, one,
 * or many correct Options; the UI always renders checkboxes uniformly
 * (see ExerciseView) rather than branching on option count.
 */
export interface ExerciseOption {
  id: number
  text: string
}

export interface ExerciseQuestion {
  id: number
  text: string
  options: ExerciseOption[]
}

export interface ExerciseQuestionSet {
  id: number
  title: string
  questions: ExerciseQuestion[]
}

/** Response shape from `POST /api/exercise-attempts`. See ADR-0014. */
export interface ExerciseGradeResult {
  score: number
  total: number
  results: Array<{
    correctOptionIds: number[]
    submittedOptionIds: number[]
    isCorrect: boolean
    explanation: string | null
  }>
}

/** `exercise` page template — a quiz attached to a learning path. See ADR 0011 / ADR-0014. */
export interface ExerciseContent {
  title: string
  url: string | null
  intro: string | null
  questionSet: ExerciseQuestionSet | null
}
