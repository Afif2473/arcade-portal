variable "aws_region" {
  type        = string
  description = "AWS region for all resources"
  default     = "ap-southeast-2"
}

variable "project" {
  type        = string
  description = "Project name prefix"
  default     = "game-portal"
}

variable "github_owner" {
  type        = string
  description = "GitHub username or organization owning the repo"
  default     = "Afif2473"
}

variable "github_repo" {
  type        = string
  description = "GitHub repository name"
  default     = "web-platform"
}

variable "game_slug" {
  type        = string
  description = "Default game slug for CI/CD scoping"
  default     = "test-harness"
}
