<?php
// Set environment variables for testing
putenv('JWT_SECRET_KEY=+WQFBKMY3oH7qSqi0Vx+3kW5RA8PI/zCCTOCw8NFaELLVKNvtuxqVPadVTm5JqQIPjGbNT9FU1YT7juByrFSdg==');
putenv('SUPABASE_URL=https://kgqwiwjayaydewyuygxt.supabase.co');
putenv('SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.icrGci0zm7HppVhF5BNnXZiBwLgtj2s8am2cHOdwtho');

// Include and run the test
require_once __DIR__ . '/test_token_verify.php';
