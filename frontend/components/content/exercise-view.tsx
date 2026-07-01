"use client"

import { useState } from "react"
import Link from "next/link"
import { CheckCircle, XCircle } from "@phosphor-icons/react"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import type { ExerciseContent, MultipleChoiceBlock } from "./types"

type QuizState = {
  answers: (string | null)[]
  submitted: boolean
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
  pathSlug,
}: {
  content: ExerciseContent
  pathSlug?: string
}) {
  const questions = content.questions ?? []
  const [state, setState] = useState<QuizState>({
    answers: questions.map(() => null),
    submitted: false,
  })

  const allAnswered =
    questions.length > 0 && state.answers.every((a) => a !== null)
  const score = state.answers.filter(
    (a, i) => a === questions[i]?.correct
  ).length

  function selectAnswer(index: number, option: string) {
    if (state.submitted) return
    setState((s) => {
      const answers = [...s.answers]
      answers[index] = option
      return { ...s, answers }
    })
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
            submitted={state.submitted}
            onSelect={(option) => selectAnswer(i, option)}
          />
        ))}
      </div>

      <div className="mt-14 border-t pt-10">
        {!state.submitted ? (
          <Button
            type="button"
            size="lg"
            disabled={!allAnswered}
            onClick={() => setState((s) => ({ ...s, submitted: true }))}
          >
            Submit
          </Button>
        ) : (
          <p className="text-lg font-semibold">
            Score: {score} / {questions.length}
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
  submitted,
  onSelect,
}: {
  index: number
  question: MultipleChoiceBlock
  selected: string | null
  submitted: boolean
  onSelect: (option: string) => void
}) {
  return (
    <fieldset className="space-y-3">
      <legend className="font-medium">
        {index + 1}. {question.question}
      </legend>
      <div className="space-y-2">
        {OPTIONS.map(({ key, field }) => {
          const isSelected = selected === key
          const isCorrect = key === question.correct

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
      {submitted && question.explanation ? (
        <p className="rounded border-l-4 border-sky-500 bg-sky-500/8 p-3 text-sm text-muted-foreground">
          {question.explanation}
        </p>
      ) : null}
    </fieldset>
  )
}
