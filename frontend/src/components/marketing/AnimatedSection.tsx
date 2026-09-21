import { type ReactNode } from 'react'
import { motion, useReducedMotion, type HTMLMotionProps } from 'framer-motion'
import { fadeUp, motionOrReduced, staggerContainer } from '../../utils/marketingMotion'

type AnimatedSectionProps = {
  children: ReactNode
  className?: string
  as?: 'div' | 'section' | 'header' | 'article'
  stagger?: boolean
} & Omit<HTMLMotionProps<'div'>, 'children' | 'initial' | 'whileInView' | 'animate' | 'variants'>

export function AnimatedSection({
  children,
  className,
  stagger = false,
  ...rest
}: AnimatedSectionProps) {
  const reduceMotion = useReducedMotion()
  const variants = motionOrReduced(reduceMotion, stagger ? staggerContainer : fadeUp)

  return (
    <motion.div
      className={className}
      variants={variants}
      initial="hidden"
      whileInView="show"
      viewport={{ once: true, amount: 0.18 }}
      {...rest}
    >
      {children}
    </motion.div>
  )
}

export function MotionItem({
  children,
  className,
}: {
  children: ReactNode
  className?: string
}) {
  const reduceMotion = useReducedMotion()
  return (
    <motion.div className={className} variants={motionOrReduced(reduceMotion, fadeUp)}>
      {children}
    </motion.div>
  )
}
