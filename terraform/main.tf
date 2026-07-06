terraform {
  required_version = ">= 1.5"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.region
}

# Canonical Ubuntu 22.04 LTS. "Most recent" means a future `terraform apply`
# can legitimately replace the instance when AWS publishes a newer AMI —
# the Elastic IP below is what makes that non-disruptive to the Ansible
# inventory (the address stays stable; only Tailscale needs to rejoin, see
# docs/operations/failover-runbook.md).
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"] # Canonical

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}

resource "aws_key_pair" "secondary" {
  key_name   = "architecture-hub-secondary"
  public_key = var.ssh_public_key

  tags = local.tags
}

resource "aws_security_group" "secondary" {
  name        = "architecture-hub-secondary"
  description = "Secondary backend instance (EC2 failover drill) - SSH + HTTP only, no direct DB exposure"

  ingress {
    description = "SSH"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.allowed_cidr]
  }

  ingress {
    description = "HTTP (nginx, proxies to the local backend)"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = [var.allowed_cidr]
  }

  egress {
    description = "All outbound (GHCR pulls, apt, Tailscale DERP relay, etc.)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = local.tags
}

resource "aws_instance" "secondary" {
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = var.instance_type
  key_name               = aws_key_pair.secondary.key_name
  vpc_security_group_ids = [aws_security_group.secondary.id]

  root_block_device {
    volume_size = var.root_volume_size
    volume_type = "gp3"
  }

  tags = merge(local.tags, {
    Name = "architecture-hub-secondary"
  })
}

resource "aws_eip" "secondary" {
  instance = aws_instance.secondary.id
  domain   = "vpc"

  tags = local.tags
}

locals {
  tags = {
    Project     = "architecture-hub-showcase"
    Environment = "training"
    ManagedBy   = "terraform"
  }
}
