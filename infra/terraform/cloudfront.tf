resource "aws_cloudfront_origin_access_control" "games" {
  name                              = "${var.project}-oac"
  origin_access_control_origin_type = "s3"
  signing_behavior                  = "always"
  signing_protocol                  = "sigv4"
}

data "aws_cloudfront_cache_policy" "optimized" {
  name = "Managed-CachingOptimized"
}

resource "aws_cloudfront_distribution" "games" {
  enabled      = true
  comment      = "${var.project} game asset CDN"
  price_class  = "PriceClass_All"
  http_version = "http2and3"

  origin {
    domain_name              = aws_s3_bucket.games.bucket_regional_domain_name
    origin_id                = "s3-games"
    origin_access_control_id = aws_cloudfront_origin_access_control.games.id
  }

  default_cache_behavior {
    target_origin_id       = "s3-games"
    viewer_protocol_policy = "redirect-to-https"
    allowed_methods        = ["GET", "HEAD"]
    cached_methods         = ["GET", "HEAD"]
    compress               = true
    cache_policy_id        = data.aws_cloudfront_cache_policy.optimized.id
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  viewer_certificate {
    cloudfront_default_certificate = true
  }
}
