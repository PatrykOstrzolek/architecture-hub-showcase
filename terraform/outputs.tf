output "instance_id" {
  description = "EC2 instance ID of the secondary backend."
  value       = aws_instance.secondary.id
}

output "public_ip" {
  description = "Stable Elastic IP of the secondary backend — set this as the PRODUCTION_HOST_EC2 GitHub Secret."
  value       = aws_eip.secondary.public_ip
}
