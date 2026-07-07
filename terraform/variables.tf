variable "region" {
  description = "AWS region to provision the secondary backend instance in."
  type        = string
  default     = "eu-central-1"
}

variable "instance_type" {
  description = "EC2 instance type. t2.micro is the classic Free Tier type, but newer accounts (post-2025-credit-model) may not have it Free-Tier-eligible at all — confirmed via `aws ec2 describe-instance-types --filters Name=free-tier-eligible,Values=true` that t3.micro is eligible instead in that case. Check the 'Free Tier eligible' badge in your own AWS console, or run that query, before applying."
  type        = string
  default     = "t3.micro"
}

variable "ssh_public_key" {
  description = "Public half of the SSH key used to reach this instance (e.g. output of `ssh-keygen -y -f <private key>`). Matches the key whose private half is stored in the SSH_PRIVATE_KEY GitHub Secret."
  type        = string
}

variable "allowed_cidr" {
  description = "CIDR allowed to reach SSH (22) and HTTP (80). Defaults to open, matching Mikrus's own already-open ufw posture (22/80/443 allowed from anywhere) — tighten to your own IP for a free hardening step if you want one."
  type        = string
  default     = "0.0.0.0/0"
}

variable "root_volume_size" {
  description = "Root EBS volume size in GB. 20 GB stays comfortably under the 30 GB/month Free Tier allowance while giving headroom over the AMI's 8 GB default once Docker images/logs accumulate."
  type        = number
  default     = 20
}
