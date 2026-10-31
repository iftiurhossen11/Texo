import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import type { ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { api } from '../api/client'

interface AnalyticsData {
  total_indexed: number
  total_audits_prepared: number
  by_department: Array<{ department: string; count: number }>
  expired_certificates: number
  pending_tasks: number
  readiness_score: number
  weekly_queries: Array<{ week: string; queries: number }>
}

const departmentLabels: Record<string, string> = {
  qa: 'QA',
  compliance_sustainability: 'Compliance',
  hr_training: 'HR',
  it_cybersecurity: 'Cyber',
  production: 'Production',
  supply_chain_merchandising: 'Supply Chain',
  general: 'General',
}

export function AnalyticsDashboard() {
  const [analytics, setAnalytics] = useState<AnalyticsData | null>(null)
  const [error, setError] = useState(false)

  useEffect(() => {
    let active = true
    void api.get<AnalyticsData>('/analytics')
      .then(({ data }) => {
        if (active) setAnalytics(data)
      })
      .catch(() => {
        if (active) setError(true)
      })
    return () => {
      active = false
    }
  }, [])

  if (error) {
    return <EmptyDashboard message="Analytics are temporarily unavailable." />
  }

  if (!analytics) {
    return <EmptyDashboard message="Loading analytics…" />
  }

  if (analytics.total_indexed === 0) {
    return <EmptyDashboard message="No documents indexed yet. Upload a document to see analytics." />
  }

  const departments = analytics.by_department.map((item) => ({
    department: departmentLabels[item.department] ?? item.department,
    documents: item.count,
  }))
  const readinessData = analytics.total_indexed > 0
    ? [
        { name: 'Ready', value: analytics.readiness_score, color: '#34d399' },
        { name: 'Missing Certificates', value: 10, color: '#fbbf24' },
        { name: 'Expired', value: analytics.expired_certificates, color: '#f87171' },
      ]
    : []

  return (
    <section className="max-h-[calc(100vh-15rem)] overflow-y-auto rounded-3xl border border-slate-700 bg-slate-900 p-5 md:p-8">
      <div className="flex flex-wrap items-end justify-between gap-4 border-b border-slate-800 pb-6">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-400">
            Operations Intelligence
          </p>
          <h2 className="mt-2 text-3xl font-bold text-white">Audit Readiness Dashboard</h2>
          <p className="mt-2 text-slate-400">
            A live exhibition view of Texo knowledge coverage and factory readiness.
          </p>
        </div>
      </div>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total Documents Indexed" value={analytics.total_indexed} detail="Knowledge base coverage" />
        <StatCard label="Total Audits Prepared" value={analytics.total_audits_prepared} detail="This reporting period" />
        <StatCard label="Expired Certificates" value={analytics.expired_certificates} detail="Requires attention" warning />
        <StatCard label="Pending Tasks" value={analytics.pending_tasks} detail="Open compliance actions" />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_2fr]">
        <section className="flex flex-col items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">
            Overall Audit Readiness
          </p>
          <div
            className="mt-5 grid h-48 w-48 place-items-center rounded-full"
            style={{ background: `conic-gradient(#34d399 0deg ${analytics.readiness_score * 3.6}deg, #1e293b ${analytics.readiness_score * 3.6}deg 360deg)` }}
          >
            <div className="grid h-36 w-36 place-items-center rounded-full bg-slate-900">
              <span className="text-5xl font-bold text-white">{analytics.readiness_score}%</span>
            </div>
          </div>
          <p className="mt-4 max-w-xs text-sm leading-6 text-slate-400">
            Strong readiness with certificate renewal and pending-task follow-up still required.
          </p>
        </section>

        <ChartCard title="Documents by Department">
          {departments.length ? <ResponsiveContainer width="100%" height="100%">
            <BarChart data={departments} margin={{ top: 10, right: 12, left: -20, bottom: 24 }}>
              <CartesianGrid stroke="#334155" strokeDasharray="3 3" />
              <XAxis dataKey="department" tick={{ fill: '#94a3b8', fontSize: 12 }} />
              <YAxis allowDecimals={false} tick={{ fill: '#94a3b8', fontSize: 12 }} />
              <Tooltip
                contentStyle={{ backgroundColor: '#0f172a', borderColor: '#334155', color: '#f8fafc' }}
              />
              <Bar dataKey="documents" fill="#34d399" radius={[6, 6, 0, 0]} />
            </BarChart>
          </ResponsiveContainer> : <NoData />}
        </ChartCard>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <ChartCard title="Audit Readiness Status">
          {readinessData.length ? <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie data={readinessData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={90} label>
                {readinessData.map((entry) => (
                  <Cell key={entry.name} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip
                contentStyle={{ backgroundColor: '#0f172a', borderColor: '#334155', color: '#f8fafc' }}
              />
            </PieChart>
          </ResponsiveContainer> : <NoData />}
        </ChartCard>

        <ChartCard title="Queries per Week">
          {analytics.weekly_queries.length ? <ResponsiveContainer width="100%" height="100%">
            <LineChart data={analytics.weekly_queries} margin={{ top: 10, right: 12, left: -20, bottom: 0 }}>
              <CartesianGrid stroke="#334155" strokeDasharray="3 3" />
              <XAxis dataKey="week" tick={{ fill: '#94a3b8', fontSize: 12 }} />
              <YAxis allowDecimals={false} tick={{ fill: '#94a3b8', fontSize: 12 }} />
              <Tooltip
                contentStyle={{ backgroundColor: '#0f172a', borderColor: '#334155', color: '#f8fafc' }}
              />
              <Line type="monotone" dataKey="queries" stroke="#22d3ee" strokeWidth={3} dot={{ fill: '#22d3ee' }} />
            </LineChart>
          </ResponsiveContainer> : <NoData />}
        </ChartCard>
      </div>
    </section>
  )
}

function StatCard({
  label,
  value,
  detail,
  warning = false,
}: {
  label: string
  value: number
  detail: string
  warning?: boolean
}) {
  return (
    <article className={`rounded-2xl border p-5 ${warning ? 'border-red-500/40 bg-red-500/10' : 'border-slate-700 bg-slate-950/60'}`}>
      <p className={`text-sm ${warning ? 'text-red-200' : 'text-slate-400'}`}>{label}</p>
      <p className={`mt-2 text-3xl font-bold ${warning ? 'text-red-300' : 'text-white'}`}>{value}</p>
      <p className={`mt-1 text-xs ${warning ? 'text-red-200/70' : 'text-slate-500'}`}>{detail}</p>
    </article>
  )
}

function ChartCard({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="rounded-2xl border border-slate-700 bg-slate-950/60 p-5">
      <h3 className="text-lg font-semibold text-white">{title}</h3>
      <div className="mt-4 h-72 min-h-[300px] overflow-visible">{children}</div>
    </section>
  )
}

function NoData() {
  return <div className="grid h-full place-items-center text-sm text-slate-500">No data yet</div>
}

function EmptyDashboard({ message }: { message: string }) {
  return (
    <section className="rounded-3xl border border-slate-700 bg-slate-900 p-8 text-center text-slate-400">
      {message}
    </section>
  )
}
