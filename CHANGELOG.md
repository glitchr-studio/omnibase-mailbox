# Changelog

## [Unreleased]

### Fixed
- The compose form refuses a subject longer than `mailbox.subject_max_length`, and only then:
  `ComposeModel` capped it at 55 whatever the option, so a site that raised it still had longer
  subjects refused. The constraint is the form's (`ComposeType`'s `subject_max_length` option).
- README: omnisense/omnibase on GitHub in place of base-bundle on the abandoned gitlab.glitchr.dev;
  the demo image named omnibase-mailbox-demo.
