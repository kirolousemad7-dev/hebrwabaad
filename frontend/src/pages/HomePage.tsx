import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { AboutSection } from '../components/landing/AboutSection'
import { BuildPackageSection } from '../components/landing/BuildPackageSection'
import { ContactSection } from '../components/landing/ContactSection'
import { FinalCta } from '../components/landing/FinalCta'
import { HeroSection } from '../components/landing/HeroSection'
import { PackagesSection } from '../components/landing/PackagesSection'
import { PortfolioSection } from '../components/landing/PortfolioSection'
import { ProcessSection } from '../components/landing/ProcessSection'
import { ServicesSection } from '../components/landing/ServicesSection'
import { SuppliersNetworkSection } from '../components/landing/SuppliersNetworkSection'
import { WhyUsSection } from '../components/landing/WhyUsSection'

export function HomePage() {
  const location = useLocation()

  useEffect(() => {
    const hash = location.hash.replace(/^#/, '')
    if (!hash) return

    const timer = window.setTimeout(() => {
      document.getElementById(hash)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }, 50)

    return () => window.clearTimeout(timer)
  }, [location.hash])

  return (
    <>
      <HeroSection />
      <ServicesSection />
      <WhyUsSection />
      <PackagesSection />
      <BuildPackageSection />
      <ProcessSection />
      <PortfolioSection compact />
      <SuppliersNetworkSection />
      <AboutSection />
      <FinalCta />
      <ContactSection />
    </>
  )
}
