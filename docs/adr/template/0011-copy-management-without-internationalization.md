# Copy management without internationalization

The template uses shared keys for server-rendered copy to prevent duplication and validation drift;
copy in `resources/js` stays inline. Localization remains app-owned: locale-prefixed URLs change the
public contract, while client dictionaries are a mechanical retrofit.
