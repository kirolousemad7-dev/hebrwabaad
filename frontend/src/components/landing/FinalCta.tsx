import { LandingCta } from './LandingCta'

function scrollToId(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

export function FinalCta() {
  return (
    <section id="final-cta" className="bg-brand-ink-900 py-20 text-white">
      <div className="mx-auto max-w-4xl px-4 text-center sm:px-6">
        <h2 className="text-3xl font-semibold sm:text-4xl">جاهز نبدأ مشروعك؟</h2>
        <p className="mx-auto mt-4 max-w-2xl leading-8 text-white/70">
          احكِ لنا عن فكرتك ودع فريق حبر وأبعاد يحولها إلى تجربة متكاملة.
        </p>
        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <LandingCta
            href="#contact"
            onClick={(event) => {
              event.preventDefault()
              scrollToId('contact')
            }}
          >
            ابدأ مشروعك
          </LandingCta>
          <LandingCta
            href="#contact"
            variant="light"
            onClick={(event) => {
              event.preventDefault()
              scrollToId('contact')
            }}
          >
            تواصل معنا
          </LandingCta>
        </div>
      </div>
    </section>
  )
}
