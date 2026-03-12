# Performance Raw Results Report

- Date: 2026-03-10 06:26:54 UTC
- Environment: nginx 1.24 + php-fpm 8.5 on localhost:8000
- Baseline reference: `docs/performance/PERFORMANCE_BASELINE_2026-03-09.md`
- Benchmark tenant: TechMart (`edc5531d-207c-4187-b52b-dbf974764910`)
- Tenant-visible dataset: 336 products, 18 categories, 72 customers, 170 orders, 36 invoices, 18 promotions
- Sprint 3 fixture total reference: 1,008 products, 54 categories, 216 customers, 510 orders, 108 invoices, 54 promotions across 3 tenants
- Methodology: 5 serial iterations per endpoint, metric = `X-Response-Time`, headers include `X-Tenant-ID` and `Accept-Language: en`
- Auth user for protected endpoints: `admin@techmart.com`
- Login benchmark credentials: `staff@techmart.com`

## Exact Sprint 2 Endpoint Benchmarks

### POST /api/login_check

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 200 | 393.80ms |
| 2 | 200 | 399.64ms |
| 3 | 200 | 399.50ms |
| 4 | 200 | 391.06ms |
| 5 | 200 | 399.45ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 391.06ms | 396.69ms | 399.64ms | 399.64ms |

### GET /api/v1/products

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 200 | 369.82ms |
| 2 | 200 | 410.99ms |
| 3 | 200 | 464.75ms |
| 4 | 200 | 483.29ms |
| 5 | 200 | 421.24ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 369.82ms | 430.02ms | 483.29ms | 483.29ms |

### GET /api/v1/categories

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 200 | 36.54ms |
| 2 | 200 | 34.13ms |
| 3 | 200 | 35.49ms |
| 4 | 200 | 34.55ms |
| 5 | 200 | 33.69ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 33.69ms | 34.88ms | 36.54ms | 36.54ms |

### GET /api/v1/orders

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 200 | 326.77ms |
| 2 | 200 | 282.80ms |
| 3 | 200 | 278.54ms |
| 4 | 200 | 253.02ms |
| 5 | 200 | 267.09ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 253.02ms | 281.64ms | 326.77ms | 326.77ms |

### GET /api/v1/customers

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 200 | 68.51ms |
| 2 | 200 | 67.46ms |
| 3 | 200 | 68.87ms |
| 4 | 200 | 65.22ms |
| 5 | 200 | 65.53ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 65.22ms | 67.12ms | 68.87ms | 68.87ms |

### GET /api/v1/invoices

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 200 | 84.78ms |
| 2 | 200 | 85.24ms |
| 3 | 200 | 98.78ms |
| 4 | 200 | 84.14ms |
| 5 | 200 | 86.24ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 84.14ms | 87.84ms | 98.78ms | 98.78ms |

### GET /api/v1/returns

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 404 | 9.64ms |
| 2 | 404 | 10.13ms |
| 3 | 404 | 10.88ms |
| 4 | 404 | 9.61ms |
| 5 | 404 | 10.52ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 9.61ms | 10.16ms | 10.88ms | 10.88ms |

### GET /api/v1/promotions

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 200 | 71.51ms |
| 2 | 200 | 48.35ms |
| 3 | 200 | 44.27ms |
| 4 | 200 | 47.44ms |
| 5 | 200 | 48.13ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 44.27ms | 51.94ms | 71.51ms | 71.51ms |

### GET /api/v1/wishlists

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 404 | 10.60ms |
| 2 | 404 | 9.84ms |
| 3 | 404 | 11.70ms |
| 4 | 404 | 10.34ms |
| 5 | 404 | 9.93ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 9.84ms | 10.48ms | 11.70ms | 11.70ms |

### GET /api/v1/reviews

| Iteration | Status | X-Response-Time |
|---|---:|---:|
| 1 | 404 | 18.21ms |
| 2 | 404 | 9.95ms |
| 3 | 404 | 10.23ms |
| 4 | 404 | 11.02ms |
| 5 | 404 | 10.09ms |

| Min | Avg | p95 | Max |
|---:|---:|---:|---:|
| 9.95ms | 11.90ms | 18.21ms | 18.21ms |

## Route Drift Notes

- The Sprint 2 paths `/api/v1/returns`, `/api/v1/wishlists`, and `/api/v1/reviews` now return 404 in the current API surface.
- Supplemental spot checks for current routes are included below where a direct replacement collection route exists.

| Label | Current route | Status | X-Response-Time |
|---|---|---:|---:|
| Returns current collection | /api/v1/return-requests?page=1&itemsPerPage=10 | 200 | 33.34ms |
| Wishlist current route | /api/v1/storefront/wishlist | 200 | 31.02ms |

## Load Test: Products Endpoint

- Tool: `ab` (ApacheBench)
- Target: `GET /api/v1/products?page=1&itemsPerPage=10`
- Options: `-k -n 500`
- Metric family: client-observed latency and throughput from `ab` output

### 10 concurrent requests

```text
This is ApacheBench, Version 2.3 <$Revision: 1903618 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking 127.0.0.1 (be patient).....done


Server Software:        nginx/1.24.0
Server Hostname:        127.0.0.1
Server Port:            8000

Document Path:          /api/v1/products?page=1&itemsPerPage=10
Document Length:        714766 bytes

Concurrency Level:      10
Time taken for tests:   84.495 seconds
Complete requests:      500
Failed requests:        0
Keep-Alive requests:    0
Total transferred:      358222500 bytes
HTML transferred:       357383000 bytes
Requests per second:    5.92 [#/sec] (mean)
Time per request:       1689.892 [ms] (mean)
Time per request:       168.989 [ms] (mean, across all concurrent requests)
Transfer rate:          4140.23 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.4      0       5
Processing:  1420 1646 175.7   1628    3139
Waiting:     1414 1638 175.3   1620    3134
Total:       1421 1646 175.7   1628    3139

Percentage of the requests served within a certain time (ms)
  50%   1628
  66%   1671
  75%   1698
  80%   1712
  90%   1772
  95%   1835
  98%   1926
  99%   2636
 100%   3139 (longest request)
```

### 50 concurrent requests

```text
This is ApacheBench, Version 2.3 <$Revision: 1903618 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking 127.0.0.1 (be patient).....done


Server Software:        nginx/1.24.0
Server Hostname:        127.0.0.1
Server Port:            8000

Document Path:          /api/v1/products?page=1&itemsPerPage=10
Document Length:        714766 bytes

Concurrency Level:      50
Time taken for tests:   82.502 seconds
Complete requests:      500
Failed requests:        0
Keep-Alive requests:    0
Total transferred:      358222500 bytes
HTML transferred:       357383000 bytes
Requests per second:    6.06 [#/sec] (mean)
Time per request:       8250.171 [ms] (mean)
Time per request:       165.003 [ms] (mean, across all concurrent requests)
Transfer rate:          4240.24 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    1   0.7      0       8
Processing:  1476 7745 1223.3   7874    9111
Waiting:     1465 7737 1223.3   7866    9105
Total:       1476 7746 1223.1   7874    9112
WARNING: The median and mean for the initial connection time are not within a normal deviation
        These results are probably not that reliable.

Percentage of the requests served within a certain time (ms)
  50%   7874
  66%   8267
  75%   8326
  80%   8382
  90%   8646
  95%   8784
  98%   8853
  99%   8880
 100%   9112 (longest request)
```

### 100 concurrent requests

```text
This is ApacheBench, Version 2.3 <$Revision: 1903618 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking 127.0.0.1 (be patient).....done


Server Software:        nginx/1.24.0
Server Hostname:        127.0.0.1
Server Port:            8000

Document Path:          /api/v1/products?page=1&itemsPerPage=10
Document Length:        714766 bytes

Concurrency Level:      100
Time taken for tests:   85.207 seconds
Complete requests:      500
Failed requests:        0
Keep-Alive requests:    0
Total transferred:      358222512 bytes
HTML transferred:       357383000 bytes
Requests per second:    5.87 [#/sec] (mean)
Time per request:       17041.415 [ms] (mean)
Time per request:       170.414 [ms] (mean, across all concurrent requests)
Transfer rate:          4105.61 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    1   1.9      0      31
Processing:  1452 15207 3808.6  16411   18273
Waiting:     1436 15199 3808.2  16398   18265
Total:       1453 15208 3807.8  16413   18273

Percentage of the requests served within a certain time (ms)
  50%  16413
  66%  16930
  75%  17191
  80%  17383
  90%  17704
  95%  18094
  98%  18210
  99%  18245
 100%  18273 (longest request)
```
