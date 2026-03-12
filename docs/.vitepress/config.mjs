import { defineConfig } from 'vitepress'

const repo = 'https://github.com/MrAdder/filament-logger'

export default defineConfig({
  title: 'Filament Logger',
  description: 'Audit logging, exports, alerts, and dashboards for Filament admin panels.',
  base: process.env.GITHUB_ACTIONS === 'true' ? '/filament-logger/' : '/',
  cleanUrls: true,
  lastUpdated: true,
  themeConfig: {
    logo: '/mark.svg',
    nav: [
      { text: 'Guide', link: '/installation' },
      { text: 'Review UI', link: '/activity-review' },
      { text: 'GitHub', link: repo },
      { text: 'Packagist', link: 'https://packagist.org/packages/mradder/filament-logger' }
    ],
    search: {
      provider: 'local'
    },
    socialLinks: [
      { icon: 'github', link: repo }
    ],
    footer: {
      message: 'Built for Filament teams that need better audit visibility.',
      copyright: 'MIT Licensed'
    },
    editLink: {
      pattern: 'https://github.com/MrAdder/filament-logger/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    },
    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Installation and Setup', link: '/installation' },
          { text: 'Security and Authorization', link: '/security' }
        ]
      },
      {
        text: 'Configuration',
        items: [
          { text: 'Configuration Guide', link: '/configuration' },
          { text: 'Activity Review UI', link: '/activity-review' },
          { text: 'Custom Events and Alerts', link: '/custom-events' }
        ]
      },
      {
        text: 'Project',
        items: [
          { text: 'Releasing', link: '/releasing' }
        ]
      }
    ]
  }
})
