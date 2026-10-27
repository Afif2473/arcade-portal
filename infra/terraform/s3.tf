resource "aws_s3_bucket" "games" {
  bucket = "${var.project}-assets-${data.aws_caller_identity.current.account_id}"
}

resource "aws_s3_bucket_public_access_block" "games" {
  bucket                  = aws_s3_bucket.games.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_ownership_controls" "games" {
  bucket = aws_s3_bucket.games.id
  rule {
    object_ownership = "BucketOwnerEnforced"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "games" {
  bucket = aws_s3_bucket.games.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

# S3 Bucket Policy: Only CloudFront OAC can read objects
data "aws_iam_policy_document" "games_bucket" {
  statement {
    sid       = "AllowCloudFrontOAC"
    actions   = ["s3:GetObject"]
    resources = ["${aws_s3_bucket.games.arn}/*"]

    principals {
      type        = "Service"
      identifiers = ["cloudfront.amazonaws.com"]
    }

    condition {
      test     = "StringEquals"
      variable = "AWS:SourceArn"
      values   = [aws_cloudfront_distribution.games.arn]
    }
  }
}

resource "aws_s3_bucket_policy" "games" {
  bucket     = aws_s3_bucket.games.id
  policy     = data.aws_iam_policy_document.games_bucket.json
  depends_on = [aws_s3_bucket_public_access_block.games]
}
