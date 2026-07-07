output "instance_id" {
  description = "EC2 instance ID of the secondary backend."
  value       = aws_instance.secondary.id
}

output "public_ip" {
  description = "Stable Elastic IP of the secondary backend — set this as the PRODUCTION_HOST_EC2 GitHub Secret."
  value       = aws_eip.secondary.public_ip
}

# Assumes a region other than us-east-1, which uses the legacy
# "compute-1.amazonaws.com" suffix instead — fine for this project's fixed
# eu-central-1 default, not worth a conditional for a single-region setup.
output "public_dns" {
  description = "AWS-assigned public hostname for the Elastic IP — free and stable as long as the EIP is retained, but NOT usable as a Let's Encrypt cert subject: Let's Encrypt's CA policy refuses to issue for *.amazonaws.com hostnames outright (\"forbidden by policy\", confirmed against the live ACME server). The actual TLS cert subject is a sslip.io hostname instead (see ansible/group_vars/secondary/main.yml: ec2_public_hostname). Computed rather than read from aws_instance.secondary.public_dns, which can still reflect the instance's original auto-assigned IP immediately after EIP association within the same apply."
  value       = "ec2-${replace(aws_eip.secondary.public_ip, ".", "-")}.${var.region}.compute.amazonaws.com"
}
