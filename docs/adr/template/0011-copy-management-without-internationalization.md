# Copy management without internationalization

The template owns server-rendered user-facing copy as `lang/` keys and owns nothing else about
localization: locale-prefixed URLs, locale detection, and a client-side dictionary stay app-owned,
and copy in `resources/js` stays inline. The line follows the asymmetry in retrofit cost. A locale
prefix is an externally visible contract that rewrites every existing URL once a site is public, so
it belongs to each application; extracting copy into keys and adding a client dictionary change no
contract and can be applied mechanically later. Copy management itself is not deferred, because
inline literals already cost the template today: one sentence duplicates across a controller and a
service, and a form request drifts from the shared `validation.attributes` defaults.
