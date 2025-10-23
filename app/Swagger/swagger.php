<?php

/**
 * @OA\Info(
 *     title="Yeksina Delivery API",
 *     version="1.0.0",
 *     description="API pour l'application de livraison Yeksina - Système complet de gestion de livraisons",
 *     @OA\Contact(
 *         email="support@yeksina.com",
 *         name="Support Yeksina"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Serveur de développement local"
 * )
 * 
 * @OA\Server(
 *     url="https://api.yeksina.com",
 *     description="Serveur de production"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoints d'authentification et d'inscription"
 * )
 * 
 * @OA\Tag(
 *     name="Deliveries", 
 *     description="Gestion des livraisons"
 * )
 * 
 * @OA\Tag(
 *     name="Driver",
 *     description="Espace driver - gestion des livraisons et profil"
 * )
 * 
 * @OA\Tag(
 *     name="Admin",
 *     description="Administration - gestion des drivers et supervision"
 * )
 * 
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Opération réussie"),
 *     @OA\Property(property="data", type="object")
 * )
 * 
 * @OA\Schema(
 *     schema="ErrorResponse", 
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Une erreur est survenue"),
 *     @OA\Property(property="errors", type="object", example={})
 * )
 */
