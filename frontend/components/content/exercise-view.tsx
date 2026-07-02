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
  ExerciseQuestion,
} from "./types"

type QuizState = {
  /** Per question, the set of selected option ids — empty array = unanswered. */
  answers: number[][]
  submitting: boolean
  error: string | null
  result: ExerciseGradeResult | null
}

export function ExerciseView({
  content,
  exerciseId,
  pathSlug,
}: {
  content: ExerciseContent
  exerciseId: string
  pathSlug?: string
}) {
  const questions = content.questionSet?.questions ?? []
  const [state, setState] = useState<QuizState>({
    answers: questions.map(() => []),
    submitting: false,
    error: null,
    result: null,
  })

  const allAnswered =
    questions.length > 0 && state.answers.every((a) => a.length > 0)
  const submitted = state.result !== null

  function toggleOption(index: number, optionId: number) {
    if (submitted) return
    setState((s) => {
      const answers = [...s.answers]
      const current = answers[index] ?? []
      answers[index] = current.includes(optionId)
        ? current.filter((id) => id !== optionId)
        : [...current, optionId]
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
            key={question.id}
            index={i}
            question={question}
            selected={state.answers[i] ?? []}
            result={state.result?.results[i] ?? null}
            onToggle={(optionId) => toggleOption(i, optionId)}
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
  onToggle,
}: {
  index: number
  question: ExerciseQuestion
  selected: number[]
  result: ExerciseGradeResult["results"][number] | null
  onToggle: (optionId: number) => void
}) {
  const submitted = result !== null

  return (
    <fieldset className="space-y-3">
      <legend className="font-medium">
        {index + 1}. {question.text}
      </legend>
      <div className="space-y-2">
        {question.options.map((option) => {
          const isSelected = selected.includes(option.id)
          const isCorrectOption =
            submitted && result.correctOptionIds.includes(option.id)

          return (
            <label
              key={option.id}
              className={cn(
                "flex cursor-pointer items-center justify-between gap-3 rounded border p-3 transition-colors",
                submitted && "cursor-default",
                !submitted && isSelected && "border-primary bg-primary/5",
                !submitted && !isSelected && "hover:bg-accent",
                submitted &&
                  isCorrectOption &&
                  "border-emerald-500 bg-emerald-500/8",
                submitted &&
                  isSelected &&
                  !isCorrectOption &&
                  "border-red-500 bg-red-500/8"
              )}
            >
              <span className="flex items-center gap-3">
                <input
                  type="checkbox"
                  checked={isSelected}
                  disabled={submitted}
                  onChange={() => onToggle(option.id)}
                  className="shrink-0"
                />
                <span>{option.text}</span>
              </span>
              {submitted && isCorrectOption ? (
                <CheckCircle
                  weight="fill"
                  className="size-4 shrink-0 text-emerald-500"
                />
              ) : null}
              {submitted && isSelected && !isCorrectOption ? (
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
