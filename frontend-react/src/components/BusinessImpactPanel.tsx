import { useState } from 'react'
import jsPDF from 'jspdf'

const subscriptionCost = 2388
const savingsPerManager = 6600
const hoursPerManager = 156

const pricingTiers = [
  {
    name: 'Startup',
    price: '$49/mo',
    description: 'A practical starting point for smaller factories.',
    features: ['500 documents', '5 users', 'Basic RBAC', 'English PDF export'],
  },
  {
    name: 'Pro',
    price: '$199/mo',
    description: 'Complete audit-readiness support for growing teams.',
    features: ['Unlimited documents', '20 users', 'Compliance Gap Finder', 'Advanced analytics'],
    popular: true,
  },
  {
    name: 'Enterprise',
    price: 'Custom',
    description: 'Secure, integrated deployment for factory groups.',
    features: ['Cloud deployment', 'SSO and API access', 'Dedicated support', 'Custom governance'],
  },
]

const factoryProblems = [
  {
    problem: 'Audit Fatigue & High Costs',
    loss: 'Factories face 3.6 social audits per facility per year (ITC Report), at approximately $2,000 per audit (BKMEA). Missing documents can contribute to failed audits and $100,000+ in lost orders (Risk Assessment).',
    solution: 'Gap Finder flags missing certificates before the audit, while instant SOP retrieval reduces preparation time by 50%.',
  },
  {
    problem: 'Knowledge is Scattered',
    loss: 'QA managers waste approximately 5 hours per week searching folders and shared drives for SOPs and procedures.',
    solution: 'One conversational interface searches factory documents and returns instant, source-cited answers.',
  },
  {
    problem: 'Employee Turnover & Knowledge Loss',
    loss: 'When senior staff leave, undocumented knowledge leaves with them and training new employees costs thousands.',
    solution: 'Texo acts as a Knowledge Vault so new employees can ask questions and learn from historical documents instantly.',
  },
  {
    problem: 'Data Security & Confidentiality',
    loss: 'Costing files, HR policies, and sensitive procedures could be exposed to unauthorized staff.',
    solution: 'Role-Based Access Control ensures each worker sees only documents permitted for their role.',
  },
  {
    problem: 'Slow Onboarding & Training',
    loss: 'New hires can take weeks to become productive when policies and procedures are difficult to find.',
    solution: 'Suggested Questions and instant answers help new workers learn factory policies from Day 1.',
  },
]

const trustedUseCases = [
  { label: 'QA', detail: 'Defect rules & AQL limits', color: 'text-cyan-300', icon: '✓' },
  { label: 'Compliance', detail: 'Audit readiness & buyer rules', color: 'text-emerald-300', icon: '◈' },
  { label: 'HR', detail: 'People policies & training', color: 'text-violet-300', icon: '◎' },
  { label: 'Cyber', detail: 'Phishing & secure operations', color: 'text-amber-300', icon: '⌁' },
]

const competitorRows = [
  { feature: 'Role-Based Access', texo: true, rag: false, spreadsheets: false },
  { feature: 'Compliance Gap Finder', texo: true, rag: false, spreadsheets: false },
  { feature: 'PDF Export', texo: true, rag: false, spreadsheets: true },
]

const marketContext = [
  'Bangladesh has approximately 4,000 garment factories (GISWatch), with 80%+ of exports from RMG (Cascale).',
  'Only 32.3% of surveyed firms use AI in supply chain management (Master’s Thesis, 2025), showing a huge market opportunity.',
  '2.7 million apparel workers are at risk from automation (ILO, 2024), making Texo’s assistant, not replacement positioning critical.',
  'Factories face 3.6 social audits per year, often asking for the same documents repeatedly (ITC Report).',
]

export function BusinessImpactPanel() {
  const [managerCount, setManagerCount] = useState(3)

  const managers = Math.max(1, Math.min(100, managerCount || 1))
  const annualSavings = managers * savingsPerManager
  const netBenefit = annualSavings - subscriptionCost
  const roi = Math.round((netBenefit / subscriptionCost) * 100)

  const downloadBusinessPlan = () => {
    const pdf = new jsPDF()
    const pageWidth = pdf.internal.pageSize.getWidth()
    const pageHeight = pdf.internal.pageSize.getHeight()
    const margin = 20
    const contentWidth = pageWidth - margin * 2
    let y = 0
    const cleanPdfText = (value: string): string => {
      const currencyToken = '__TEXO_CURRENCY__'
      const percentToken = '__TEXO_PERCENT__'

      return value
        .replace(/\\\$/g, currencyToken)
        .replace(/\$/g, currencyToken)
        .replace(/\\%/g, percentToken)
        .replace(/%/g, percentToken)
        .replace(/[\\()$%]/g, '')
        .replace(/\*\*/g, '')
        .replace(/^#+\s*/gm, '')
        .replace(/\s{2,}/g, ' ')
        .replaceAll(currencyToken, '$')
        .replaceAll(percentToken, '%')
        .trim()
    }

    pdf.setDocumentProperties({ title: 'Texo Business Plan' })
    const addHeader = (pageTitle: string, pageNumber: number) => {
      pdf.setFillColor(7, 24, 48)
      pdf.rect(0, 0, pageWidth, pageHeight, 'F')
      pdf.setTextColor(236, 253, 245)
      pdf.setFont('helvetica', 'bold')
      pdf.setFontSize(18)
      pdf.text('TEXO BUSINESS PLAN', margin, 22)
      pdf.setFontSize(10)
      pdf.setTextColor(110, 231, 183)
      pdf.text(cleanPdfText(pageTitle), margin, 31)
      pdf.setDrawColor(52, 211, 153)
      pdf.line(margin, 37, pageWidth - margin, 37)
      pdf.setFont('helvetica', 'normal')
      pdf.setFontSize(9)
      pdf.setTextColor(148, 163, 184)
      pdf.text(cleanPdfText(`Texo Business Value | Page ${pageNumber} of 4`), pageWidth - margin, pageHeight - 12, { align: 'right' })
      y = 52
    }
    const heading = (text: string) => {
      pdf.setFont('helvetica', 'bold')
      pdf.setFontSize(15)
      pdf.setTextColor(110, 231, 183)
      pdf.text(cleanPdfText(text), margin, y)
      y += 9
    }
    const paragraph = (text: string, size = 10, gap = 7) => {
      pdf.setFont('helvetica', 'normal')
      pdf.setFontSize(size)
      pdf.setTextColor(226, 232, 240)
      const wrapped = pdf.splitTextToSize(cleanPdfText(text), contentWidth)
      pdf.text(wrapped, margin, y)
      y += wrapped.length * 5 + gap
    }
    const metric = (label: string, value: string) => {
      pdf.setFont('helvetica', 'normal')
      pdf.setFontSize(10)
      pdf.setTextColor(203, 213, 225)
      pdf.text(cleanPdfText(label), margin, y)
      pdf.setFont('helvetica', 'bold')
      pdf.setTextColor(255, 255, 255)
      pdf.text(cleanPdfText(value), pageWidth - margin, y, { align: 'right' })
      y += 9
    }

    addHeader('Financial Impact and ROI', 1)
    heading('Financial Impact')
    paragraph(`Save $${annualSavings.toLocaleString()}/year with Texo Pro.`)
    paragraph(`Scenario: ${managers} QA Managers. This report matches the current ROI Calculator selection.`)
    y += 3
    metric('Hours saved per year', `${managers * hoursPerManager} hours`)
    metric('Estimated annual savings', `$${annualSavings.toLocaleString()}`)
    metric('Texo Pro annual cost', `$${subscriptionCost.toLocaleString()}`)
    metric('Estimated net benefit', `$${netBenefit.toLocaleString()}`)
    metric('Net ROI', `${roi}%`)
    y += 8
    heading('Executive Summary')
    paragraph('Texo gives textile and apparel factories a secure knowledge layer for approved procedures, audit preparation, and institutional knowledge. Role-based access keeps information visible only to the teams that need it.')

    pdf.addPage()
    addHeader('Pricing Tiers', 2)
    heading('Pricing Tiers')
    pricingTiers.forEach((tier) => {
      pdf.setFont('helvetica', 'bold')
      pdf.setFontSize(12)
      pdf.setTextColor(255, 255, 255)
      pdf.text(cleanPdfText(`${tier.name} - ${tier.price}${tier.popular ? ' (Most Popular)' : ''}`), margin, y)
      y += 7
      paragraph(tier.description, 10, 3)
      tier.features.forEach((feature) => paragraph(`- ${feature}`, 10, 2))
      y += 5
    })
    heading('Value at a Glance')
    paragraph('The Pro tier combines unlimited document access, compliance gap finding, role-based controls, and analytics for teams preparing for recurring buyer and social compliance audits.')

    pdf.addPage()
    addHeader('Risk Reduction and Factory Solutions', 3)
    heading('Cost of Inaction')
    paragraph('Without Texo, a failed audit can contribute to $100,000+ in lost orders. Texo helps teams find approved procedures, certificates, and buyer requirements before an auditor asks for them.')
    heading('Market Context from Research')
    marketContext.forEach((point) => paragraph(`- ${point}`, 9, 2))
    heading('Real-World Problems and Texo Solutions')
    factoryProblems.forEach((item) => {
      pdf.setFont('helvetica', 'bold')
      pdf.setFontSize(11)
      pdf.setTextColor(251, 191, 36)
      pdf.text(cleanPdfText(item.problem), margin, y)
      y += 6
      paragraph(`Loss: ${item.loss}`, 9, 2)
      pdf.setTextColor(110, 231, 183)
      paragraph(`Texo Solution: ${item.solution}`, 9, 5)
    })

    pdf.addPage()
    addHeader('Use Cases and Competitive Position', 4)
    heading('Trusted Use Cases')
    trustedUseCases.forEach((useCase) => {
      paragraph(`${useCase.label}: ${useCase.detail}`, 10, 3)
    })
    heading('Competitor Comparison')
    competitorRows.forEach((row) => {
      paragraph(`${row.feature}: Texo ${row.texo ? 'Yes' : 'No'} | Generic RAG Chatbot ${row.rag ? 'Yes' : 'No'} | Manual Spreadsheets ${row.spreadsheets ? 'Yes' : 'No'}`, 9, 3)
    })
    heading('Customer Voice')
    paragraph('"Texo addresses the 3.6 social audits per facility per year in Bangladesh, reducing the $2,000 cost per audit and preventing $100,000+ in lost orders from failed audits." - Industry Research, Bangladesh (2023)')
    pdf.setFont('helvetica', 'normal')
    pdf.setFontSize(9)
    pdf.setTextColor(148, 163, 184)
    pdf.text(cleanPdfText('Prepared by Texo | Business Value Overview'), margin, pageHeight - 24)
    pdf.save('Texo_Business_Plan.pdf')
  }

  return (
    <section className="max-h-[calc(100vh-15rem)] overflow-y-auto rounded-3xl border border-slate-700 bg-slate-900 p-5 shadow-2xl md:p-8">
        <div className="border-b border-slate-800 pb-6">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-400">
              Business Value
            </p>
            <h2 id="business-impact-title" className="mt-2 text-3xl font-bold text-white">
              Why Factories Buy Texo
            </h2>
            <p className="mt-2 text-slate-400">Save Time. Reduce Audit Risk. Protect Knowledge.</p>
          </div>
        </div>

        <section className="mt-6 overflow-hidden rounded-3xl border border-emerald-400/40 bg-gradient-to-br from-emerald-500/30 via-teal-500/15 to-slate-950 p-6 shadow-[0_0_45px_rgba(16,185,129,0.12)] md:p-8">
          <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div>
              <p className="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-200">
                Financial Impact
              </p>
              <p className="mt-3 text-3xl font-black tracking-tight text-white md:text-5xl">
                Save $19,800/year with Texo Pro
              </p>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-emerald-50/80">
                Based on the default three-manager scenario. Adjust the calculator below to see how the impact scales with your team.
              </p>
            </div>
            <button
              type="button"
              onClick={downloadBusinessPlan}
              className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-emerald-200/50 bg-emerald-100 px-4 py-3 text-sm font-bold text-emerald-950 transition hover:bg-white"
            >
              <svg viewBox="0 0 24 24" className="h-4 w-4 fill-none stroke-current stroke-2" aria-hidden="true">
                <path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
              </svg>
              Download Business Plan (PDF)
            </button>
          </div>
        </section>

        <section className="mt-6 rounded-2xl border border-slate-700 bg-slate-950/50 p-5">
          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Trusted use cases</p>
              <h3 className="mt-1 text-lg font-semibold text-white">One knowledge layer for every critical team</h3>
            </div>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
              {trustedUseCases.map((useCase) => (
                <div key={useCase.label} className="rounded-2xl border border-slate-800 bg-slate-900 p-4 transition hover:border-emerald-500/50">
                  <span className={`flex h-11 w-11 items-center justify-center rounded-xl bg-slate-800 text-xl font-black ${useCase.color}`} aria-hidden="true">
                    {useCase.icon}
                  </span>
                  <span className="mt-3 block text-sm font-semibold text-slate-100">{useCase.label}</span>
                  <span className="mt-1 block text-xs leading-5 text-slate-400">{useCase.detail}</span>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="mt-6 w-full rounded-2xl border border-slate-700 bg-slate-950/50 p-5 md:p-6">
          <div className="mb-4">
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
              Competitor Comparison
            </p>
            <h3 className="mt-1 text-xl font-bold text-white">Built for controlled, audit-ready operations</h3>
          </div>
          <div className="overflow-x-auto rounded-xl border border-slate-800">
            <table className="w-full min-w-[620px] text-left text-sm">
              <thead className="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-4 py-3 font-semibold">Capability</th>
                  <th className="px-4 py-3 font-semibold text-emerald-300">Texo</th>
                  <th className="px-4 py-3 font-semibold">Generic RAG Chatbot</th>
                  <th className="px-4 py-3 font-semibold">Manual Spreadsheets</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800 bg-slate-950/70">
                {competitorRows.map((row) => (
                  <tr key={row.feature} className="text-slate-300">
                    <th className="px-4 py-3 font-medium text-slate-200">{row.feature}</th>
                    {[row.texo, row.rag, row.spreadsheets].map((supported, index) => (
                      <td key={`${row.feature}-${index}`} className={`px-4 py-3 font-semibold ${supported ? 'text-emerald-300' : 'text-slate-600'}`}>
                        {supported ? '✓' : '—'}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <blockquote className="mt-6 w-full rounded-2xl border-l-4 border-emerald-400 bg-emerald-500/10 px-5 py-4 text-slate-200">
          <p className="text-lg italic leading-7">
            “Texo addresses the 3.6 social audits per facility per year in Bangladesh, reducing the $2,000 cost per audit and preventing $100,000+ in lost orders from failed audits.”
          </p>
          <footer className="mt-2 text-sm text-emerald-300">— Industry Research, Bangladesh (2023)</footer>
        </blockquote>

        <section className="mt-6 w-full rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5">
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
              Interactive ROI Calculator
            </p>
            <label className="mt-5 block text-sm text-slate-300" htmlFor="qa-manager-count">
              Number of QA Managers
            </label>
            <input
              id="qa-manager-count"
              type="number"
              min={1}
              max={100}
              value={managerCount}
              onChange={(event) => setManagerCount(Number(event.target.value))}
              className="mt-2 w-full rounded-xl border border-slate-600 bg-slate-950 px-4 py-3 text-lg font-semibold text-white outline-none focus:border-emerald-400"
            />
            <div className="mt-5 space-y-3">
              <Metric label="Hours saved per year" value={`${managers * hoursPerManager} hours`} />
              <Metric label="Estimated cost savings" value={`$${annualSavings.toLocaleString()} / year`} />
              <Metric label="Texo Pro subscription cost" value="$2,388 / year" />
              <div className="rounded-xl border border-emerald-400/40 bg-emerald-400/10 p-4">
                <p className="text-xs uppercase tracking-wide text-emerald-300">Net ROI</p>
                <p className="mt-1 text-3xl font-bold text-emerald-200">{roi}%</p>
                <p className="mt-1 text-xs text-emerald-100/70">
                  ${netBenefit.toLocaleString()} estimated annual net benefit
                </p>
              </div>
            </div>
            <p className="mt-4 text-xs leading-5 text-slate-400">
              Baseline: $6,600 annual savings and 156 hours saved per QA manager, based on faster SOP lookup and audit preparation.
            </p>
        </section>

        <section className="mt-6 w-full">
          <div className="grid w-full gap-4 md:grid-cols-3">
              {pricingTiers.map((tier) => (
                <article
                  key={tier.name}
                  className={`relative rounded-2xl border p-5 ${
                    tier.popular
                      ? 'border-emerald-400 bg-emerald-500/10'
                      : 'border-slate-700 bg-slate-950/50'
                  }`}
                >
                  {tier.popular && (
                    <span className="absolute -top-3 right-4 rounded-full bg-emerald-400 px-3 py-1 text-xs font-bold text-slate-950">
                      Most Popular
                    </span>
                  )}
                  <h3 className="text-lg font-semibold text-white">{tier.name}</h3>
                  <p className="mt-2 text-2xl font-bold text-emerald-300">{tier.price}</p>
                  <p className="mt-3 min-h-12 text-sm text-slate-400">{tier.description}</p>
                  <ul className="mt-4 space-y-2 text-sm text-slate-200">
                    {tier.features.map((feature) => (
                      <li key={feature} className="flex gap-2">
                        <span className="text-emerald-400" aria-hidden="true">✓</span>
                        <span>{feature}</span>
                      </li>
                    ))}
                  </ul>
                </article>
              ))}
          </div>

          <div className="mt-6 rounded-2xl border border-orange-500/40 bg-orange-500/10 p-5">
              <p className="text-sm font-semibold uppercase tracking-wide text-orange-300">
                Cost of Inaction
              </p>
              <p className="mt-2 text-lg font-semibold text-orange-100">
                Without Texo: a failed audit can cost $100,000+ in lost orders.
              </p>
              <p className="mt-2 text-sm leading-6 text-orange-100/70">
                Texo helps teams find approved procedures, certificates, and buyer requirements before an auditor asks for them.
              </p>
              <div className="mt-5 border-t border-orange-300/20 pt-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-orange-200">
                  Market Context from Research
                </p>
                <ul className="mt-3 space-y-2 text-sm leading-6 text-orange-100/80">
                  {marketContext.map((point) => (
                    <li key={point} className="flex gap-2">
                      <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-orange-300" aria-hidden="true" />
                      <span>{point}</span>
                    </li>
                  ))}
                </ul>
              </div>
          </div>
        </section>

        <section className="mt-8 w-full">
              <div>
                <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
                  Factory Problems, Losses &amp; Texo Solutions
                </p>
                <h3 className="mt-2 text-2xl font-bold text-white">
                  Real-World Problems &amp; How Texo Mitigates Them
                </h3>
                <p className="mt-2 text-sm leading-6 text-slate-400">
                  Texo turns recurring operational risks into measurable opportunities for faster, safer, and more consistent factory work.
                </p>
              </div>

          <div className="mt-5 grid w-full gap-4 md:grid-cols-2">
                {factoryProblems.map((item) => (
                  <article
                    key={item.problem}
                    className="grid w-full gap-4 rounded-2xl border border-slate-700 bg-slate-950/60 p-5"
                  >
                    <div className="border-l-2 border-orange-400 pl-4">
                      <div className="flex items-start gap-2">
                        <span className="text-lg text-orange-400" aria-hidden="true">⚠</span>
                        <h4 className="font-semibold text-orange-100">{item.problem}</h4>
                      </div>
                      <p className="mt-2 text-sm leading-6 text-slate-300">{item.loss}</p>
                    </div>
                    <div className="border-l-2 border-emerald-400 pl-4">
                      <div className="flex items-start gap-2">
                        <span className="text-lg text-emerald-400" aria-hidden="true">✓</span>
                        <h4 className="font-semibold text-emerald-100">Texo Solution</h4>
                      </div>
                      <p className="mt-2 text-sm leading-6 text-slate-300">{item.solution}</p>
                    </div>
                  </article>
                ))}
          </div>
        </section>
    </section>
  )
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3 border-b border-emerald-500/20 pb-3">
      <span className="text-sm text-slate-300">{label}</span>
      <span className="text-right font-semibold text-white">{value}</span>
    </div>
  )
}
