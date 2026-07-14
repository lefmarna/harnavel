import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const ROOT = path.resolve(__dirname, '..')
const RENOVATE_PATH =
  process.env.RENOVATE_CONFIG ?? path.join(ROOT, 'renovate.json')

interface PackageRule {
  enabled?: boolean
  matchCurrentVersion?: string
  matchManagers?: string[]
  matchUpdateTypes?: string[]
  rangeStrategy?: string
}

interface RenovateConfig {
  extends?: string[]
  packageRules?: PackageRule[]
}

interface DisabledUpdateRule {
  currentVersion?: string
  updateType: string
}

const REQUIRED_DISABLED_UPDATES: DisabledUpdateRule[] = [
  { updateType: 'major' },
  { currentVersion: '<1.0.0', updateType: 'minor' },
  { currentVersion: '<0.1.0', updateType: 'patch' },
  { updateType: 'replacement' },
]

function isComposerRule(rule: PackageRule): boolean {
  return rule.matchManagers?.includes('composer') ?? false
}

function findDisabledRuleIndex(
  rules: PackageRule[],
  required: DisabledUpdateRule,
): number {
  return rules.findIndex(
    (rule) =>
      isComposerRule(rule) &&
      rule.enabled === false &&
      rule.matchUpdateTypes?.includes(required.updateType) === true &&
      rule.matchCurrentVersion === required.currentVersion,
  )
}

function main(): void {
  const config = JSON.parse(
    fs.readFileSync(RENOVATE_PATH, 'utf8'),
  ) as RenovateConfig
  const rules = config.packageRules ?? []

  if (!config.extends?.includes('config:recommended')) {
    throw new Error('Renovate must continue to extend config:recommended.')
  }

  const rangeRule = rules.find(
    (rule) => isComposerRule(rule) && rule.rangeStrategy !== undefined,
  )

  if (rangeRule?.rangeStrategy !== 'update-lockfile') {
    throw new Error(
      'Composer must use update-lockfile for updates that remain within the declared constraint.',
    )
  }

  const composerGroupIndex = rules.findIndex(
    (rule) =>
      isComposerRule(rule) &&
      rule.matchUpdateTypes?.includes('minor') === true &&
      rule.matchUpdateTypes.includes('patch'),
  )

  if (composerGroupIndex === -1) {
    throw new Error('Composer minor and patch grouping rule was not found.')
  }

  for (const required of REQUIRED_DISABLED_UPDATES) {
    const index = findDisabledRuleIndex(rules, required)
    const versionDescription =
      required.currentVersion === undefined
        ? ''
        : ` for current versions ${required.currentVersion}`

    if (index === -1) {
      throw new Error(
        `Composer ${required.updateType} updates${versionDescription} must be disabled.`,
      )
    }

    if (index <= composerGroupIndex) {
      throw new Error(
        `Composer ${required.updateType} deny rule must appear after the minor/patch grouping rule.`,
      )
    }
  }

  console.log(
    'OK: Renovate keeps routine Composer updates within existing constraint strings.',
  )
}

main()
