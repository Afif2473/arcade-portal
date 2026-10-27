output "bucket_name" {
  description = "The name of the private S3 game assets bucket"
  value       = aws_s3_bucket.games.bucket
}

output "cloudfront_domain" {
  description = "The CloudFront CDN domain name serving the games"
  value       = aws_cloudfront_distribution.games.domain_name
}

output "cloudfront_distribution_id" {
  description = "The CloudFront distribution ID"
  value       = aws_cloudfront_distribution.games.id
}

output "backend_iam_user" {
  description = "IAM username for Laravel backend uploads"
  value       = aws_iam_user.backend_local.name
}

output "gh_deployer_iam_user" {
  description = "IAM username for GitHub Actions CI/CD scoped deployer"
  value       = aws_iam_user.gh_deployer.name
}
