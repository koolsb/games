<?php

// Lightweight liveness/readiness endpoint. The Kubernetes probes hit this
// directly so they never need to boot the full framework.

http_response_code(200);
header('Content-Type: text/plain');
echo 'OK';
