# Generated by Django 3.2.11 on 2022-06-02 14:58

from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('vuln_app', 'initial'),
    ]

    operations = [
        migrations.AlterField(
            model_name='user',
            name='id',
            field=models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID'),
        ),
    ]
