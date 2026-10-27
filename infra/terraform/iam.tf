# -----------------------------------------------------------------------------
# 1. Laravel Local Backend IAM User (Direct Zip Ingestion)
# -----------------------------------------------------------------------------
resource "aws_iam_user" "backend_local" {
  name = "${var.project}-backend-local"
}

data "aws_iam_policy_document" "backend_s3" {
  statement {
    sid       = "ReadWriteGameObjects"
    actions   = ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"]
    resources = ["${aws_s3_bucket.games.arn}/games/*"]
  }

  statement {
    sid       = "ListGamePrefix"
    actions   = ["s3:ListBucket"]
    resources = [aws_s3_bucket.games.arn]

    condition {
      test     = "StringLike"
      variable = "s3:prefix"
      values   = ["games/*"]
    }
  }
}

resource "aws_iam_user_policy" "backend_s3" {
  name   = "game-portal-backend-s3"
  user   = aws_iam_user.backend_local.name
  policy = data.aws_iam_policy_document.backend_s3.json
}

# -----------------------------------------------------------------------------
# 2. GitHub Actions CI/CD Scoped Deployer User (Least Privilege)
# -----------------------------------------------------------------------------
resource "aws_iam_user" "gh_deployer" {
  name = "${var.project}-gh-deployer"
}

data "aws_iam_policy_document" "gh_deployer_s3" {
  statement {
    sid       = "WriteOwnGamePrefixOnly"
    actions   = ["s3:PutObject", "s3:DeleteObject"]
    resources = ["${aws_s3_bucket.games.arn}/games/${var.game_slug}/*"]
  }

  statement {
    sid       = "ListOwnGamePrefixOnly"
    actions   = ["s3:ListBucket"]
    resources = [aws_s3_bucket.games.arn]

    condition {
      test     = "StringLike"
      variable = "s3:prefix"
      values   = ["games/${var.game_slug}/*"]
    }
  }
}

resource "aws_iam_user_policy" "gh_deployer_s3" {
  name   = "s3-own-game-prefix"
  user   = aws_iam_user.gh_deployer.name
  policy = data.aws_iam_policy_document.gh_deployer_s3.json
}
