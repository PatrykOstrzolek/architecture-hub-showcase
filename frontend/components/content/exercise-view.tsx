"use client"

import { useState } from "react"
import Link from "next/link"
import { CheckCircle, XCircle } from "@phosphor-icons/react"
import { cn } from "@/lib/utils"
import { getAnonymousSessionId } from "@/lib/anonymous-session"
import { Button } from "@/components/ui/button"
import type {
  ExerciseContent,
  ExerciseGradeResult,
  MultipleChoiceBlock,
} from "./types"

type QuizState = {
  answers: (string | null)[]
  submitting: boolean
  error: string | null
  result: ExerciseGradeResult | null
}

const OPTIONS: Array<{
  key: "a" | "b" | "c" | "d"
  field: keyof MultipleChoiceBlock
}> = [
  { key: "a", field: "option_a" },
  { key: "b", field: "option_b" },
  { key: "c", field: "option_c" },
  { key: "d", field: "option_d" },
]

export function ExerciseView({
  content,
  exerciseId,
  pathSlug,
}: {
  content: ExerciseContent
  exerciseId: string
  pathSlug?: string
}) {
  const questions = content.questions ?? []
  const [state, setState] = useState<QuizState>({
    answers: questions.map(() => null),
    submitting: false,
    error: null,
    result: null,
  })

  const allAnswered =
    questions.length > 0 && state.answers.every((a) => a !== null)
  const submitted = state.result !== null

  function selectAnswer(index: number, option: string) {
    if (submitted) return
    setState((s) => {
      const answers = [...s.answers]
      answers[index] = option
      return { ...s, answers }
    })
  }

  async function submit() {
    setState((s) => ({ ...s, submitting: true, error: null }))
    try {
      const res = await fetch("/api/exercise-attempts", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          exerciseUuid: exerciseId,
          sessionId: getAnonymousSessionId(),
          answers: state.answers,
        }),
      })
      if (!res.ok) throw new Error("Submission failed")
      const result = (await res.json()) as ExerciseGradeResult
      setState((s) => ({ ...s, submitting: false, result }))
    } catch {
      setState((s) => ({
        ...s,
        submitting: false,
        error: "Couldn't submit your answers. Please try again.",
      }))
    }
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <div className="mb-10">
        <Link
          href={pathSlug ? `/learning-paths/${pathSlug}` : "/learning-paths"}
          className="font-mono text-xs text-muted-foreground transition-colors hover:text-foreground"
        >
          ← back to path
        </Link>
      </div>

      <header className="mb-10">
        <p className="mb-3 font-mono text-[10px] tracking-widest text-primary uppercase">
          Exercise
        </p>
        <h1 className="text-4xl leading-tight font-bold tracking-tight">
          {content.title}
        </h1>
        {content.intro ? (
          <p className="mt-4 text-lg leading-7 text-muted-foreground">
            {content.intro}
          </p>
        ) : null}
        <div className="mt-8 border-b" />
      </header>

      <div className="space-y-10">
        {questions.map((question, i) => (
          <QuestionCard
            key={i}
            index={i}
            question={question}
            selected={state.answers[i]}
            result={state.result?.results[i] ?? null}
            onSelect={(option) => selectAnswer(i, option)}
          />
        ))}
      </div>

      <div className="mt-14 border-t pt-10">
        {!submitted ? (
          <>
            <Button
              type="button"
              size="lg"
              disabled={!allAnswered || state.submitting}
              onClick={submit}
            >
              {state.submitting ? "Submitting…" : "Submit"}
            </Button>
            {state.error ? (
              <p className="mt-4 text-sm text-red-500">{state.error}</p>
            ) : null}
          </>
        ) : (
          <p className="text-lg font-semibold">
            Score: {state.result?.score} / {state.result?.total}
          </p>
        )}
      </div>
    </div>
  )
}

function QuestionCard({
  index,
  question,
  selected,
  result,
  onSelect,
}: {
  index: number
  question: MultipleChoiceBlock
  selected: string | null
  result: ExerciseGradeResult["results"][number] | null
  onSelect: (option: string) => void
}) {
  const submitted = result !== null

  return (
    <fieldset className="space-y-3">
      <legend className="font-medium">
        {index + 1}. {question.question}
      </legend>
      <div className="space-y-2">
        {OPTIONS.map(({ key, field }) => {
          const isSelected = selected === key
          const isCorrect = submitted && key === result.correct

          return (
            <label
              key={key}
              className={cn(
                "flex cursor-pointer items-center justify-between gap-3 rounded border p-3 transition-colors",
                submitted && "cursor-default",
                !submitted && isSelected && "border-primary bg-primary/5",
                !submitted && !isSelected && "hover:bg-accent",
                submitted && isCorrect && "border-emerald-500 bg-emerald-500/8",
                submitted &&
                  isSelected &&
                  !isCorrect &&
                  "border-red-500 bg-red-500/8"
              )}
            >
              <span className="flex items-center gap-3">
                <input
                  type="radio"
                  name={`question-${index}`}
                  checked={isSelected}
                  disabled={submitted}
                  onChange={() => onSelect(key)}
                  className="shrink-0"
                />
                <span>{question[field] as string}</span>
              </span>
              {submitted && isCorrect ? (
                <CheckCircle
                  weight="fill"
                  className="size-4 shrink-0 text-emerald-500"
                />
              ) : null}
              {submitted && isSelected && !isCorrect ? (
                <XCircle
                  weight="fill"
                  className="size-4 shrink-0 text-red-500"
                />
              ) : null}
            </label>
          )
        })}
      </div>
      {submitted && result.explanation ? (
        <p className="rounded border-l-4 border-sky-500 bg-sky-500/8 p-3 text-sm text-muted-foreground">
          {result.explanation}
        </p>
      ) : null}
    </fieldset>
  )
}
