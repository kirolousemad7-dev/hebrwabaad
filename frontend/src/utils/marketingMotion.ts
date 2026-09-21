import type { Variants, Transition } from 'framer-motion'

const easeOut: Transition['ease'] = [0.22, 1, 0.36, 1]

export const marketingTransition = {
  default: { duration: 0.55, ease: easeOut } satisfies Transition,
  slow: { duration: 0.75, ease: easeOut } satisfies Transition,
  fast: { duration: 0.35, ease: easeOut } satisfies Transition,
  springSoft: { type: 'spring', stiffness: 120, damping: 18 } satisfies Transition,
}

export const fadeUp: Variants = {
  hidden: { opacity: 0, y: 28 },
  show: { opacity: 1, y: 0, transition: marketingTransition.default },
}

export const fadeIn: Variants = {
  hidden: { opacity: 0 },
  show: { opacity: 1, transition: marketingTransition.default },
}

export const slideLeft: Variants = {
  hidden: { opacity: 0, x: 36 },
  show: { opacity: 1, x: 0, transition: marketingTransition.slow },
}

export const slideRight: Variants = {
  hidden: { opacity: 0, x: -36 },
  show: { opacity: 1, x: 0, transition: marketingTransition.slow },
}

export const scaleIn: Variants = {
  hidden: { opacity: 0, scale: 0.94 },
  show: { opacity: 1, scale: 1, transition: marketingTransition.default },
}

export const imageReveal: Variants = {
  hidden: { opacity: 0, scale: 1.06, filter: 'blur(8px)' },
  show: {
    opacity: 1,
    scale: 1,
    filter: 'blur(0px)',
    transition: marketingTransition.slow,
  },
}

export const staggerContainer: Variants = {
  hidden: {},
  show: {
    transition: {
      staggerChildren: 0.1,
      delayChildren: 0.08,
    },
  },
}

export const staggerFast: Variants = {
  hidden: {},
  show: {
    transition: {
      staggerChildren: 0.06,
      delayChildren: 0.04,
    },
  },
}

/** Instant/no-motion variants when prefers-reduced-motion is on */
export const reducedVariants: Variants = {
  hidden: { opacity: 1, y: 0, x: 0, scale: 1, filter: 'blur(0px)' },
  show: { opacity: 1, y: 0, x: 0, scale: 1, filter: 'blur(0px)', transition: { duration: 0 } },
}

export function motionOrReduced(reduce: boolean | null, variants: Variants): Variants {
  return reduce ? reducedVariants : variants
}
