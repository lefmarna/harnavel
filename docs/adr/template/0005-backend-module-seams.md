# Backend module seams

Service/Repository seams require orchestration beyond single-model CRUD. Fortify Actions already
provide that responsibility; a wrapper needs another purpose. Input normalization remains
framework-independent by keeping HTTP parsing at the delivery boundary.
